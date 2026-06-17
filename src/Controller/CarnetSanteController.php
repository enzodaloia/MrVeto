<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\User;
use App\Repository\AnimalRepository;
use App\Repository\RendezVousRepository;
use App\Repository\TraitementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CarnetSanteController extends AbstractController
{
    #[Route('/app/animaux/a/{slug}/delete', name: 'app_animaux_animal_delete', methods: ['POST'])]
    public function deleteAnimal(
        string $slug,
        Request $request,
        AnimalRepository $animalRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $animal = $animalRepository->findOneBy(['slug' => $slug, 'proprietaire' => $user]);
        if ($animal === null) {
            throw $this->createNotFoundException('Animal introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_animal_' . $animal->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_animaux');
        }

        $nom = $animal->getNom();
        $entityManager->remove($animal);
        $entityManager->flush();

        $this->addFlash('success', $nom . ' a été supprimé.');
        return $this->redirectToRoute('app_animaux');
    }

    #[Route('/app/animaux/a/{slug}/edit', name: 'app_animaux_animal_edit', methods: ['POST'])]
    public function editAnimal(
        string $slug,
        Request $request,
        AnimalRepository $animalRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $animal = $animalRepository->findOneBy(['slug' => $slug, 'proprietaire' => $user]);
        if ($animal === null) {
            throw $this->createNotFoundException('Animal introuvable.');
        }

        if (!$this->isCsrfTokenValid('edit_animal_' . $animal->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_animaux_animal', ['slug' => $slug]);
        }

        $espece = trim((string) $request->request->get('espece', ''));
        $race   = trim((string) $request->request->get('race', ''));
        $age    = trim((string) $request->request->get('age', ''));
        $poids  = trim((string) $request->request->get('poids', ''));

        if ($espece !== '') $animal->setEspece($espece);
        if ($race !== '')   $animal->setRace($race);
        if ($age !== '')    $animal->setAge($age);
        $animal->setPoids($poids !== '' ? $poids : null);

        $entityManager->flush();
        $this->addFlash('success', 'Fiche de ' . $animal->getNom() . ' mise à jour.');

        return $this->redirectToRoute('app_animaux_animal', ['slug' => $slug]);
    }

    #[Route('/app/animaux/a/{slug}', name: 'app_animaux_animal', methods: ['GET'])]
    #[Route('/app/carnet-sante/a/{slug}', name: 'app_carnet_sante_animal', methods: ['GET'])]
    public function animal(
        string $slug,
        AnimalRepository $animalRepository,
        RendezVousRepository $rendezVousRepository,
        TraitementRepository $traitementRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $selectedAnimal = $animalRepository->findOneBy([
            'slug' => $slug,
            'proprietaire' => $user,
        ]);

        if ($selectedAnimal === null) {
            throw $this->createNotFoundException('Animal introuvable.');
        }

        $animals = $this->findAnimalsForOwner($animalRepository, $user);

        return $this->renderCarnetPage($user, $animals, $selectedAnimal, $rendezVousRepository, $traitementRepository, $entityManager);
    }

    #[Route('/app/animaux', name: 'app_animaux', methods: ['GET'])]
    #[Route('/app/carnet-sante', name: 'app_carnet_sante', methods: ['GET'])]
    public function index(
        Request $request,
        AnimalRepository $animalRepository,
        RendezVousRepository $rendezVousRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isGranted('ROLE_SECRETARY')) {
            return $this->redirectToRoute('app_home');
        }

        $search = trim((string) $request->query->get('q', ''));

        $qb = $animalRepository->createQueryBuilder('a')
            ->andWhere('a.proprietaire = :owner')
            ->setParameter('owner', $user)
            ->orderBy('a.nom', 'ASC');

        if ($search !== '') {
            $qb->andWhere('a.nom LIKE :q OR a.espece LIKE :q OR a.race LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        $animals = $qb->getQuery()->getResult();

        return $this->render('animaux/index.html.twig', [
            'animals' => $animals,
            'search'  => $search,
        ]);
    }

    #[Route('/app/carnet-sante/save-animal', name: 'app_carnet_sante_save_animal', methods: ['POST'])]
    public function saveAnimal(Request $request, EntityManagerInterface $entityManager, AnimalRepository $animalRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isGranted('ROLE_SECRETARY')) {
            return $this->redirectToRoute('app_home');
        }

        $animal = new Animal();
        $animal->setProprietaire($user);
        $entityManager->persist($animal);

        $animalNom = trim((string) $request->request->get('animalNom', ''));
        $animalEspece = trim((string) $request->request->get('animalEspece', ''));
        $animalAge = trim((string) $request->request->get('animalAge', ''));

        $returnSlug = trim((string) $request->request->get('_returnSlug', ''));

        if ($animalNom === '' || $animalEspece === '' || $animalAge === '') {
            $this->addFlash('danger', 'Nom, espèce et âge sont obligatoires.');

            if ($returnSlug !== '') {
                $existing = $animalRepository->findOneBy(['slug' => $returnSlug, 'proprietaire' => $user]);
                if ($existing !== null) {
                    return $this->redirectToRoute('app_carnet_sante_animal', ['slug' => $returnSlug]);
                }
            }

            return $this->redirectToRoute('app_animaux');
        }

        $animal->setNom($animalNom);
        $animal->setEspece($animalEspece);
        $animal->setRace($request->request->get('animalRace'));
        $animal->setPoids($request->request->get('animalPoids'));
        $animal->setVaccinAJour($request->request->get('vaccin') === 'oui');
        $animal->setAge($animalAge);

        $entityManager->flush();

        $this->addFlash('success', 'Animal créé avec succès !');

        $newSlug = $animal->getSlug();
        if ($newSlug !== null && $newSlug !== '') {
            return $this->redirectToRoute('app_animaux_animal', ['slug' => $newSlug]);
        }

        return $this->redirectToRoute('app_animaux');
    }

    /**
     * @return list<Animal>
     */
    private function findAnimalsForOwner(AnimalRepository $animalRepository, User $user): array
    {
        return $animalRepository->createQueryBuilder('a')
            ->andWhere('a.proprietaire = :owner')
            ->setParameter('owner', $user)
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<Animal> $animals
     */
    private function renderCarnetPage(
        User $user,
        array $animals,
        Animal $selectedAnimal,
        RendezVousRepository $rendezVousRepository,
        TraitementRepository $traitementRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $now = new \DateTime();

        $allRdv = $rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.veterinaire', 'v')->addSelect('v')
            ->andWhere('r.client = :client')
            ->andWhere('r.animal = :animal')
            ->setParameter('client', $user)
            ->setParameter('animal', $selectedAnimal)
            ->orderBy('r.dateHeure', 'DESC')
            ->getQuery()
            ->getResult();

        // Auto-close past RDVs
        $hasUpdates = false;
        foreach ($allRdv as $rdv) {
            if ($rdv->getDateHeure() < $now && !in_array($rdv->getStatut(), ['termine', 'annule'], true)) {
                $rdv->setStatut('termine');
                $hasUpdates = true;
            }
        }
        if ($hasUpdates) {
            $entityManager->flush();
        }

        // Split: future vs history
        $rdvFuturs = array_values(array_filter($allRdv, fn($r) => $r->getStatut() !== 'annule' && $r->getDateHeure() > $now));
        usort($rdvFuturs, fn($a, $b) => $a->getDateHeure() <=> $b->getDateHeure()); // ASC: soonest first
        $rdvHistory = array_values(array_filter($allRdv, fn($r) => $r->getDateHeure() <= $now));

        // Stats
        $rdvNonAnnules = array_filter($rdvHistory, fn($r) => $r->getStatut() !== 'annule');
        $thisYear = (int) $now->format('Y');
        $rdvThisYear = count(array_filter($rdvNonAnnules, fn($r) => (int) $r->getDateHeure()->format('Y') === $thisYear));
        $firstVisit = !empty($rdvNonAnnules) ? array_values(array_reverse(array_values($rdvNonAnnules)))[0] : null;

        // Véto référent: most consulted
        $vetCounts = [];
        foreach ($rdvNonAnnules as $rdv) {
            $v = $rdv->getVeterinaire();
            if (!$v) continue;
            $id = $v->getId();
            if (!isset($vetCounts[$id])) {
                $vetCounts[$id] = ['vet' => $v, 'count' => 0, 'lastDate' => null];
            }
            $vetCounts[$id]['count']++;
            if ($vetCounts[$id]['lastDate'] === null) {
                $vetCounts[$id]['lastDate'] = $rdv->getDateHeure();
            }
        }
        $vetRef = null;
        if (!empty($vetCounts)) {
            usort($vetCounts, fn($a, $b) => $b['count'] - $a['count']);
            $vetRef = $vetCounts[0];
        }

        $traitements = $traitementRepository->findByAnimal($selectedAnimal);
        $lastVisit = !empty($rdvNonAnnules) ? array_values($rdvNonAnnules)[0] : null;

        return $this->render('animaux/show.html.twig', [
            'animal'      => $selectedAnimal,
            'rdvHistory'  => $rdvHistory,
            'rdvFuturs'   => $rdvFuturs,
            'traitements' => $traitements,
            'lastVisit'   => $lastVisit,
            'vetRef'      => $vetRef,
            'stats'       => [
                'total'      => count($rdvNonAnnules),
                'thisYear'   => $rdvThisYear,
                'firstVisit' => $firstVisit,
            ],
        ]);
    }


}