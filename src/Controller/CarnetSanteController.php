<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\User;
use App\Repository\AnimalRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CarnetSanteController extends AbstractController
{
    #[Route('/app/tableau-de-bord/a/{slug}', name: 'app_dashboard_animal', methods: ['GET'])]
    public function dashboardAnimal(
        string $slug,
        AnimalRepository $animalRepository,
        RendezVousRepository $rendezVousRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isGranted('ROLE_SECRETARY')) {
            return $this->redirectToRoute('app_home');
        }

        $selectedAnimal = $animalRepository->findOneBy([
            'slug' => $slug,
            'proprietaire' => $user,
        ]);

        if ($selectedAnimal === null) {
            throw $this->createNotFoundException('Animal introuvable.');
        }

        $animals = $this->findAnimalsForOwner($animalRepository, $user);

        return $this->renderDashboardPage($user, $animals, $selectedAnimal, $rendezVousRepository);
    }

    #[Route('/app/tableau-de-bord', name: 'app_dashboard', methods: ['GET'])]
    public function dashboardIndex(
        AnimalRepository $animalRepository,
        RendezVousRepository $rendezVousRepository,
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isGranted('ROLE_SECRETARY')) {
            return $this->redirectToRoute('app_home');
        }

        $animals = $this->findAnimalsForOwner($animalRepository, $user);
        if ($animals === []) {
            return $this->render('carnet_sante/dashboard.html.twig', [
                'animals' => [],
                'selectedAnimal' => null,
                'visitTotal' => 0,
                'lastVisit' => null,
                'nextVisit' => null,
                'recentVisits' => [],
                'lastVets' => [],
            ]);
        }

        $legacyAnimalId = $request->query->getInt('animal');
        if ($legacyAnimalId > 0) {
            foreach ($animals as $animal) {
                if ($animal->getId() === $legacyAnimalId && $animal->getSlug() !== null && $animal->getSlug() !== '') {
                    return $this->redirectToRoute('app_dashboard_animal', [
                        'slug' => $animal->getSlug(),
                    ], Response::HTTP_MOVED_PERMANENTLY);
                }
            }
        }

        $first = $animals[0];
        if ($first->getSlug() !== null && $first->getSlug() !== '') {
            return $this->redirectToRoute('app_dashboard_animal', ['slug' => $first->getSlug()]);
        }

        return $this->renderDashboardPage($user, $animals, $first, $rendezVousRepository);
    }

    #[Route('/app/carnet-sante/a/{slug}', name: 'app_carnet_sante_animal', methods: ['GET'])]
    public function animal(
        string $slug,
        AnimalRepository $animalRepository,
        RendezVousRepository $rendezVousRepository,
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

        return $this->renderCarnetPage($user, $animals, $selectedAnimal, $rendezVousRepository, $entityManager);
    }

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

        $animals = $this->findAnimalsForOwner($animalRepository, $user);
        if ($this->isGranted('ROLE_SECRETARY')) {
            return $this->redirectToRoute('app_home');
        }

        $animals = $animalRepository->createQueryBuilder('a')
            ->andWhere('a.proprietaire = :owner')
            ->setParameter('owner', $user)
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();

        if ($animals === []) {
            return $this->render('carnet_sante/index.html.twig', [
                'animals' => [],
                'selectedAnimal' => null,
                'medicalHistory' => [],
                'historyTotalCount' => 0,
                'lastVisit' => null,
            ]);
        }

        $legacyAnimalId = $request->query->getInt('animal');
        if ($legacyAnimalId > 0) {
            foreach ($animals as $animal) {
                if ($animal->getId() === $legacyAnimalId && $animal->getSlug() !== null && $animal->getSlug() !== '') {
                    return $this->redirectToRoute('app_carnet_sante_animal', [
                        'slug' => $animal->getSlug(),
                    ], Response::HTTP_MOVED_PERMANENTLY);
                }
            }
        }

        $first = $animals[0];
        if ($first->getSlug() !== null && $first->getSlug() !== '') {
            return $this->redirectToRoute('app_carnet_sante_animal', ['slug' => $first->getSlug()]);
        }

        return $this->renderCarnetPage($user, $animals, $first, $rendezVousRepository, $entityManager);
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

            return $this->redirectToRoute('app_carnet_sante');
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
            return $this->redirectToRoute('app_carnet_sante_animal', ['slug' => $newSlug]);
        }

        return $this->redirectToRoute('app_carnet_sante');
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
        EntityManagerInterface $entityManager,
    ): Response {
        $medicalHistory = $rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.veterinaire', 'v')->addSelect('v')
            ->andWhere('r.client = :client')
            ->andWhere('r.animal = :animal')
            ->andWhere('r.dateHeure < :now OR r.statut = :annule')
            ->setParameter('client', $user)
            ->setParameter('animal', $selectedAnimal)
            ->setParameter('now', new \DateTime())
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $hasUpdates = false;
        foreach ($medicalHistory as $rdv) {
            if ($rdv->getDateHeure() < new \DateTime() && !in_array($rdv->getStatut(), ['termine', 'annule'], true)) {
                $rdv->setStatut('termine');
                $hasUpdates = true;
            }
        }

        if ($hasUpdates) {
            $entityManager->flush();
        }

        $lastVisit = !empty($medicalHistory) ? $medicalHistory[0] : null;

        return $this->render('carnet_sante/index.html.twig', [
            'animals' => $animals,
            'selectedAnimal' => $selectedAnimal,
            'medicalHistory' => $medicalHistory,
            'historyTotalCount' => count($medicalHistory),
            'lastVisit' => $lastVisit,
        ]);
    }

    /**
     * @param list<Animal> $animals
     */
    private function renderDashboardPage(
        User $user,
        array $animals,
        Animal $selectedAnimal,
        RendezVousRepository $rendezVousRepository,
    ): Response {
        [$visitTotal, $lastVisit, $nextVisit, $recentVisits, $lastVets] = $this->getDashboardStats($user, $selectedAnimal, $rendezVousRepository);

        return $this->render('carnet_sante/dashboard.html.twig', [
            'animals' => $animals,
            'selectedAnimal' => $selectedAnimal,
            'visitTotal' => $visitTotal,
            'lastVisit' => $lastVisit,
            'nextVisit' => $nextVisit,
            'recentVisits' => $recentVisits,
            'lastVets' => $lastVets,
        ]);
    }

    /**
     * @return array{0:int,1:?object,2:?object,3:list<object>,4:list<object>}
     */
    private function getDashboardStats(
        User $user,
        Animal $selectedAnimal,
        RendezVousRepository $rendezVousRepository,
    ): array {
        $now = new \DateTime();
        $visitTotal = (int) $rendezVousRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.client = :client')
            ->andWhere('r.animal = :animal')
            ->setParameter('client', $user)
            ->setParameter('animal', $selectedAnimal)
            ->getQuery()
            ->getSingleScalarResult();

        $lastVisit = $rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.veterinaire', 'v')->addSelect('v')
            ->andWhere('r.client = :client')
            ->andWhere('r.animal = :animal')
            ->andWhere('r.dateHeure < :now OR r.statut = :annule')
            ->setParameter('client', $user)
            ->setParameter('animal', $selectedAnimal)
            ->setParameter('now', $now)
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $nextVisit = $rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.veterinaire', 'v')->addSelect('v')
            ->andWhere('r.client = :client')
            ->andWhere('r.animal = :animal')
            ->andWhere('r.dateHeure >= :now')
            ->andWhere('r.statut != :annule')
            ->setParameter('client', $user)
            ->setParameter('animal', $selectedAnimal)
            ->setParameter('now', $now)
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $recentVisits = $rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.veterinaire', 'v')->addSelect('v')
            ->andWhere('r.client = :client')
            ->andWhere('r.animal = :animal')
            ->andWhere('r.dateHeure < :now')
            ->andWhere('r.statut != :annule')
            ->setParameter('client', $user)
            ->setParameter('animal', $selectedAnimal)
            ->setParameter('now', $now)
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'DESC')
            ->setMaxResults(2)
            ->getQuery()
            ->getResult();

        $recentVetVisits = $rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.veterinaire', 'v')->addSelect('v')
            ->andWhere('r.client = :client')
            ->andWhere('r.animal = :animal')
            ->andWhere('r.dateHeure < :now')
            ->andWhere('r.statut != :annule')
            ->setParameter('client', $user)
            ->setParameter('animal', $selectedAnimal)
            ->setParameter('now', $now)
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $lastVets = [];
        $seenVets = [];
        foreach ($recentVetVisits as $visit) {
            $vet = $visit->getVeterinaire();
            $vetId = $vet?->getId();
            if ($vetId === null || isset($seenVets[$vetId])) {
                continue;
            }
            $seenVets[$vetId] = true;
            $lastVets[] = $vet;
            if (count($lastVets) >= 3) {
                break;
            }
        }

        return [$visitTotal, $lastVisit, $nextVisit, $recentVisits, $lastVets];
    }


}