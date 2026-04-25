<?php

namespace App\Controller;

use App\Entity\RendezVous as RendezVousEntity;
use App\Repository\RendezVousRepository;
use App\Repository\DayOfWorkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RendezVousController extends AbstractController
{
    #[Route('/mes-rendez-vous', name: 'app_rendezvous')]
    public function index(RendezVousRepository $rendezVousRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $upcomingRendezVous = $rendezVousRepository->createQueryBuilder('r')
            ->andWhere('r.client = :user')
            ->andWhere('r.dateHeure >= :now')
            ->andWhere('r.statut != :annule')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();

        $pastRendezVous = $rendezVousRepository->createQueryBuilder('r')
            ->andWhere('r.client = :user')
            ->andWhere('r.dateHeure < :now OR r.statut = :annule')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'DESC')
            ->getQuery()
            ->getResult();

        $hasUpdates = false;
        foreach ($pastRendezVous as $rdv) {
            if ($rdv->getDateHeure() < new \DateTime() && !in_array($rdv->getStatut(), ['termine', 'annule'])) {
                $rdv->setStatut('termine');
                $hasUpdates = true;
            }
        }

        if ($hasUpdates) {
            $entityManager->flush();
        }

        return $this->render('rendez_vous/index.html.twig', [
            'upcoming' => $upcomingRendezVous,
            'past' => $pastRendezVous,
        ]);
    }

    #[Route('/mes-rendez-vous/{id}/disponibilites', name: 'app_rendezvous_disponibilites', methods: ['GET'])]
    public function disponibilites(int $id, RendezVousRepository $rendezVousRepository, DayOfWorkRepository $dayOfWorkRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non autorisé'], 403);
        }

        $rdv = $rendezVousRepository->find($id);

        if (!$rdv || $rdv->getClient() !== $user) {
            return $this->json(['error' => 'Rendez-vous introuvable'], 404);
        }

        $vet = $rdv->getVeterinaire();

        // Récupérer les jours de travail du véto
        $daysOfWork = $dayOfWorkRepository->createQueryBuilder('d')
            ->innerJoin('d.jour', 'j')->addSelect('j')
            ->leftJoin('d.horaires', 'h')->addSelect('h')
            ->andWhere('d.user = :u')->setParameter('u', $vet)
            ->orderBy('j.ordre', 'ASC')
            ->getQuery()->getResult();

        $workingDays = [];
        foreach ($daysOfWork as $dow) {
            if (!$dow->isWorking()) continue;
            $ordre = $dow->getJour()->getOrdre();
            $horaire = $dow->getHoraires()->first();
            if ($horaire) {
                $workingDays[$ordre] = [
                    'm_s' => $horaire->getMorningStart() ? $horaire->getMorningStart()->format('H:i') : null,
                    'm_e' => $horaire->getMorningEnd() ? $horaire->getMorningEnd()->format('H:i') : null,
                    'a_s' => $horaire->getAfternoonStart() ? $horaire->getAfternoonStart()->format('H:i') : null,
                    'a_e' => $horaire->getAfternoonEnd() ? $horaire->getAfternoonEnd()->format('H:i') : null,
                ];
            }
        }

        // Récupérer les RDVs existants
        $now = new \DateTime();
        $limit = (clone $now)->modify('+30 days');
        
        $existingRdvs = $rendezVousRepository->createQueryBuilder('r')
            ->andWhere('r.veterinaire = :vet')
            ->andWhere('r.statut != :annule')
            ->andWhere('r.dateHeure >= :now')
            ->andWhere('r.dateHeure <= :limit')
            // Ne pas exclure le RDV actuel car son slot EST disponible pour lui-même,
            // ou alors l'exclure pour le remettre dans les dispos. On l'exclut :
            ->andWhere('r.id != :currentRdvId')
            ->setParameter('vet', $vet)
            ->setParameter('annule', 'annule')
            ->setParameter('now', new \DateTime())
            ->setParameter('limit', $limit)
            ->setParameter('currentRdvId', $rdv->getId())
            ->getQuery()->getResult();

        $bookedSlots = [];
        foreach ($existingRdvs as $existingRdv) {
            $date = $existingRdv->getDateHeure()->format('Y-m-d');
            $time = $existingRdv->getDateHeure()->format('H:i');
            $bookedSlots[$date][] = $time;
        }

        // Toujours rajouter le slot du rdv actuel dans les dates possibles si c'est dans le futur
        $currentDate = $rdv->getDateHeure()->format('Y-m-d');
        $currentTime = $rdv->getDateHeure()->format('H:i');

        $disponibilites = [];

        // Générer les créneaux pour les 30 prochains jours
        $current = clone $now;
        $current->setTime(0, 0);

        for ($i = 0; $i < 30; $i++) {
            $dayOrder = (int)$current->format('N'); // 1 = Monday, 7 = Sunday
            $dateStr = $current->format('Y-m-d');

            if (isset($workingDays[$dayOrder])) {
                $horaire = $workingDays[$dayOrder];
                $slots = [];

                // Matin
                if ($horaire['m_s'] && $horaire['m_e']) {
                    $start = \DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $horaire['m_s']);
                    $end = \DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $horaire['m_e']);
                    while ($start < $end) {
                        if ($start > new \DateTime()) { // Seulement les slots dans le futur
                            $t = $start->format('H:i');
                            if (!isset($bookedSlots[$dateStr]) || !in_array($t, $bookedSlots[$dateStr])) {
                                $slots[] = $t;
                            }
                        }
                        $start->modify('+30 minutes');
                    }
                }

                // Après-midi
                if ($horaire['a_s'] && $horaire['a_e']) {
                    $start = \DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $horaire['a_s']);
                    $end = \DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $horaire['a_e']);
                    while ($start < $end) {
                        if ($start > new \DateTime()) { // Seulement les slots dans le futur
                            $t = $start->format('H:i');
                            if (!isset($bookedSlots[$dateStr]) || !in_array($t, $bookedSlots[$dateStr])) {
                                $slots[] = $t;
                            }
                        }
                        $start->modify('+30 minutes');
                    }
                }

                // Rajouter le créneau actuel s'il a été joué ce jour là
                if ($dateStr === $currentDate && !in_array($currentTime, $slots)) {
                    $slots[] = $currentTime;
                    sort($slots); // Remettre dans l'ordre
                }

                if (!empty($slots)) {
                    $disponibilites[$dateStr] = array_unique($slots);
                }
            } else if ($dateStr === $currentDate) {
                // Si le véto ne bosse exceptionnellement plus ce jour mais que le rdv y est,
                // on permet quand même de conserver sa valeur
                $disponibilites[$dateStr] = [$currentTime];
            }

            $current->modify('+1 day');
        }

        return $this->json($disponibilites);
    }

    #[Route('/mes-rendez-vous/{id}/annuler', name: 'app_rendezvous_annuler', methods: ['POST'])]
    public function annuler(int $id, RendezVousRepository $rendezVousRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non autorisé'], 403);
        }

        $rdv = $rendezVousRepository->find($id);

        if (!$rdv || $rdv->getClient() !== $user) {
            return $this->json(['error' => 'Rendez-vous introuvable'], 404);
        }

        if ($rdv->getDateHeure() > new \DateTime()) {
            $rdv->setStatut('annule');
            $rdv->setLastActionType(RendezVousEntity::ACTION_TYPE_CANCELLED);
            $rdv->setLastActionByRole($this->resolveActionActorRole($user));
            $rdv->setLastActionAt(new \DateTime());
            $entityManager->flush();
            return $this->json(['success' => true]);
        }

        return $this->json(['error' => 'Impossible d\'annuler un rendez-vous passé'], 400);
    }

    #[Route('/mes-rendez-vous/{id}/modifier', name: 'app_rendezvous_modifier', methods: ['POST'])]
    public function modifier(int $id, \Symfony\Component\HttpFoundation\Request $request, RendezVousRepository $rendezVousRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non autorisé'], 403);
        }

        $rdv = $rendezVousRepository->find($id);

        if (!$rdv || $rdv->getClient() !== $user) {
            return $this->json(['error' => 'Rendez-vous introuvable'], 404);
        }

        if ($rdv->getDateHeure() <= new \DateTime()) {
            return $this->json(['error' => 'Impossible de modifier un rendez-vous passé'], 400);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['newDate']) || !isset($data['newTime'])) {
            return $this->json(['error' => 'Données incomplètes'], 400);
        }

        try {
            $newDateHeureStr = $data['newDate'] . ' ' . $data['newTime'];
            $newDateHeure = new \DateTime($newDateHeureStr);

            if ($newDateHeure <= new \DateTime()) {
                return $this->json(['error' => 'La nouvelle date doit être dans le futur'], 400);
            }

            // Vérifier la disponibilité (pas d'autre RDV à ce moment exact)
            $existingRdv = $rendezVousRepository->createQueryBuilder('r')
                ->andWhere('r.veterinaire = :vet')
                ->andWhere('r.dateHeure = :date')
                ->andWhere('r.statut != :annule')
                ->andWhere('r.id != :id')
                ->setParameter('vet', $rdv->getVeterinaire())
                ->setParameter('date', $newDateHeure)
                ->setParameter('annule', 'annule')
                ->setParameter('id', $rdv->getId())
                ->getQuery()
                ->getResult();
            
            if (count($existingRdv) > 0) {
                return $this->json(['error' => 'Le vétérinaire n\'est pas disponible à ce créneau (déjà réservé).'], 409);
            }

            $oldDateHeure = clone $rdv->getDateHeure();
            $rdv->setDateHeure($newDateHeure);
            $rdv->setPreviousDateHeure($oldDateHeure);
            $rdv->setLastActionType(RendezVousEntity::ACTION_TYPE_RESCHEDULED);
            $rdv->setLastActionByRole($this->resolveActionActorRole($user));
            $rdv->setLastActionAt(new \DateTime());
            // La mise à jour de la date rend implicitement l'ancienne libre (puisque liée au RDV via son ID et changée)
            $entityManager->flush();

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Format de date invalide'], 400);
        }
    }

    private function resolveActionActorRole($user): string
    {
        if (!is_object($user) || !method_exists($user, 'getRoles')) {
            return RendezVousEntity::ACTION_BY_CLIENT;
        }

        $roles = $user->getRoles();

        if (in_array('ROLE_VETO', $roles, true)) {
            return RendezVousEntity::ACTION_BY_VETERINAIRE;
        }

        if (in_array('ROLE_SECRETARY', $roles, true) || in_array('ROLE_SECRETAIRE', $roles, true)) {
            return RendezVousEntity::ACTION_BY_SECRETAIRE;
        }

        return RendezVousEntity::ACTION_BY_CLIENT;
    }
}
