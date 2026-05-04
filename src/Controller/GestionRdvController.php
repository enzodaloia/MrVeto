<?php

namespace App\Controller;

use App\Entity\RendezVous as RendezVousEntity;
use App\Entity\User;
use App\Repository\DayOfWorkRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GestionRdvController extends AbstractController
{
    #[Route('/gestion-rdv', name: 'app_gestion_rdv', methods: ['GET'])]
    public function index(Request $request, RendezVousRepository $rendezVousRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $filters = [
            'status' => $request->query->get('status', 'all'),
            'dateFrom' => $this->parseDateFilter($request->query->get('date_from'), '00:00:00'),
            'dateTo' => $this->parseDateFilter($request->query->get('date_to'), '23:59:59'),
            'client' => $request->query->get('client'),
            'animal' => $request->query->get('animal'),
        ];

        $tz = new \DateTimeZone('Europe/Paris');
        $now = new \DateTimeImmutable('now', $tz);
        $rendezVousRepository->markPastAsTermineForVeterinaire($user, $now);

        $upcoming = $rendezVousRepository->findUpcomingForVeterinaire($user);
        $history = $rendezVousRepository->findHistoryForVeterinaire($user, $filters);

        return $this->render('vet_cabinet/gestion_rdv.html.twig', [
            'upcoming' => $upcoming,
            'history' => $history,
            'filters' => [
                'status' => $filters['status'],
                'date_from' => $request->query->get('date_from'),
                'date_to' => $request->query->get('date_to'),
                'client' => $request->query->get('client'),
                'animal' => $request->query->get('animal'),
            ],
        ]);
    }

    #[Route('/gestion-rdv/{id}/disponibilites', name: 'app_gestion_rdv_disponibilites', methods: ['GET'])]
    public function disponibilites(int $id, RendezVousRepository $rendezVousRepository, DayOfWorkRepository $dayOfWorkRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non autorise'], 403);
        }

        $rdv = $rendezVousRepository->find($id);
        if (!$rdv || $rdv->getVeterinaire()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Rendez-vous introuvable'], 404);
        }

        $tz = new \DateTimeZone('Europe/Paris');
        $now = new \DateTimeImmutable('now', $tz);

        if ($rdv->getDateHeure() <= $now) {
            return $this->json(['error' => 'Rendez-vous passe'], 400);
        }

        $vet = $rdv->getVeterinaire();

        $daysOfWork = $dayOfWorkRepository->createQueryBuilder('d')
            ->innerJoin('d.jour', 'j')->addSelect('j')
            ->leftJoin('d.horaires', 'h')->addSelect('h')
            ->andWhere('d.user = :u')->setParameter('u', $vet)
            ->orderBy('j.ordre', 'ASC')
            ->getQuery()->getResult();

        $workingDays = [];
        foreach ($daysOfWork as $dow) {
            if (!$dow->isWorking()) {
                continue;
            }
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

        $limit = (clone $now)->modify('+30 days');

        $existingRdvs = $rendezVousRepository->createQueryBuilder('r')
            ->andWhere('r.veterinaire = :vet')
            ->andWhere('r.statut != :annule')
            ->andWhere('r.dateHeure >= :now')
            ->andWhere('r.dateHeure <= :limit')
            ->andWhere('r.id != :currentRdvId')
            ->setParameter('vet', $vet)
            ->setParameter('annule', 'annule')
            ->setParameter('now', $now)
            ->setParameter('limit', $limit)
            ->setParameter('currentRdvId', $rdv->getId())
            ->getQuery()->getResult();

        $bookedSlots = [];
        foreach ($existingRdvs as $existingRdv) {
            $date = $existingRdv->getDateHeure()->format('Y-m-d');
            $time = $existingRdv->getDateHeure()->format('H:i');
            $bookedSlots[$date][] = $time;
        }

        $currentDate = $rdv->getDateHeure()->format('Y-m-d');
        $currentTime = $rdv->getDateHeure()->format('H:i');

        $disponibilites = [];
        $current = (clone $now)->setTime(0, 0);

        for ($i = 0; $i < 30; $i++) {
            $dayOrder = (int) $current->format('N');
            $dateStr = $current->format('Y-m-d');

            if (isset($workingDays[$dayOrder])) {
                $horaire = $workingDays[$dayOrder];
                $slots = [];

                if ($horaire['m_s'] && $horaire['m_e']) {
                    $start = new \DateTimeImmutable($dateStr . ' ' . $horaire['m_s'], $tz);
                    $end = new \DateTimeImmutable($dateStr . ' ' . $horaire['m_e'], $tz);
                    while ($start < $end) {
                        if ($start > $now) {
                            $t = $start->format('H:i');
                            if (!isset($bookedSlots[$dateStr]) || !in_array($t, $bookedSlots[$dateStr], true)) {
                                $slots[] = $t;
                            }
                        }
                        $start = $start->modify('+30 minutes');
                    }
                }

                if ($horaire['a_s'] && $horaire['a_e']) {
                    $start = new \DateTimeImmutable($dateStr . ' ' . $horaire['a_s'], $tz);
                    $end = new \DateTimeImmutable($dateStr . ' ' . $horaire['a_e'], $tz);
                    while ($start < $end) {
                        if ($start > $now) {
                            $t = $start->format('H:i');
                            if (!isset($bookedSlots[$dateStr]) || !in_array($t, $bookedSlots[$dateStr], true)) {
                                $slots[] = $t;
                            }
                        }
                        $start = $start->modify('+30 minutes');
                    }
                }

                if ($dateStr === $currentDate && $rdv->getDateHeure() > $now && !in_array($currentTime, $slots, true)) {
                    $slots[] = $currentTime;
                    sort($slots);
                }

                if (!empty($slots)) {
                    $disponibilites[$dateStr] = array_values(array_unique($slots));
                }
            } elseif ($dateStr === $currentDate) {
                $disponibilites[$dateStr] = [$currentTime];
            }

            $current = $current->modify('+1 day');
        }

        return $this->json($disponibilites);
    }

    #[Route('/gestion-rdv/{id}/annuler', name: 'app_gestion_rdv_annuler', methods: ['POST'])]
    public function annuler(int $id, RendezVousRepository $rendezVousRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non autorise'], 403);
        }

        $rdv = $rendezVousRepository->find($id);
        if (!$rdv || $rdv->getVeterinaire()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Rendez-vous introuvable'], 404);
        }

        if ($rdv->getStatut() === 'annule') {
            return $this->json(['error' => 'Rendez-vous deja annule'], 400);
        }

        if ($rdv->getDateHeure() <= new \DateTime()) {
            return $this->json(['error' => 'Impossible d\'annuler un rendez-vous passe'], 400);
        }

        $rdv->setStatut('annule');
        $rdv->setLastActionType(RendezVousEntity::ACTION_TYPE_CANCELLED);
        $rdv->setLastActionByRole(RendezVousEntity::ACTION_BY_VETERINAIRE);
        $rdv->setLastActionAt(new \DateTime());
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/gestion-rdv/{id}/modifier', name: 'app_gestion_rdv_modifier', methods: ['POST'])]
    public function modifier(int $id, Request $request, RendezVousRepository $rendezVousRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non autorise'], 403);
        }

        $rdv = $rendezVousRepository->find($id);
        if (!$rdv || $rdv->getVeterinaire()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Rendez-vous introuvable'], 404);
        }

        if ($rdv->getStatut() === 'annule') {
            return $this->json(['error' => 'Rendez-vous deja annule'], 400);
        }

        if ($rdv->getDateHeure() <= new \DateTime()) {
            return $this->json(['error' => 'Impossible de modifier un rendez-vous passe'], 400);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['newDate']) || !isset($data['newTime'])) {
            return $this->json(['error' => 'Donnees incompletes'], 400);
        }

        try {
            $newDateHeureStr = $data['newDate'] . ' ' . $data['newTime'];
            $newDateHeure = new \DateTime($newDateHeureStr);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Format de date invalide'], 400);
        }

        if ($newDateHeure <= new \DateTime()) {
            return $this->json(['error' => 'La nouvelle date doit etre dans le futur'], 400);
        }

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
            return $this->json(['error' => 'Le veterinaire n\'est pas disponible a ce creneau.'], 409);
        }

        $oldDateHeure = clone $rdv->getDateHeure();
        $rdv->setDateHeure($newDateHeure);
        $rdv->setPreviousDateHeure($oldDateHeure);
        $rdv->setLastActionType(RendezVousEntity::ACTION_TYPE_RESCHEDULED);
        $rdv->setLastActionByRole(RendezVousEntity::ACTION_BY_VETERINAIRE);
        $rdv->setLastActionAt(new \DateTime());
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/gestion-rdv/{id}/confirmer', name: 'app_gestion_rdv_confirmer', methods: ['POST'])]
    public function confirmer(int $id, RendezVousRepository $rendezVousRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non autorise'], 403);
        }

        $rdv = $rendezVousRepository->find($id);
        if (!$rdv || $rdv->getVeterinaire()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Rendez-vous introuvable'], 404);
        }

        if ($rdv->getStatut() === 'annule') {
            return $this->json(['error' => 'Rendez-vous deja annule'], 400);
        }

        if ($rdv->getDateHeure() <= new \DateTime()) {
            return $this->json(['error' => 'Impossible de confirmer un rendez-vous passe'], 400);
        }

        if ($rdv->getStatut() === 'confirme') {
            return $this->json(['error' => 'Rendez-vous deja confirme'], 400);
        }

        $rdv->setStatut('confirme');
        $rdv->setLastActionByRole(RendezVousEntity::ACTION_BY_VETERINAIRE);
        $rdv->setLastActionAt(new \DateTime());
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function parseDateFilter(?string $date, string $timeSuffix): ?\DateTime
    {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            return new \DateTime($date . ' ' . $timeSuffix);
        } catch (\Exception $e) {
            return null;
        }
    }
}
