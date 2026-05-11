<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Traitement;
use App\Entity\User;
use App\Repository\AnimalRepository;
use App\Repository\RendezVousRepository;
use App\Repository\TraitementRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/veterinaire/patients')]
final class PatientsController extends AbstractController
{
    #[Route('', name: 'app_vet_patients', methods: ['GET'])]
    public function index(Request $request, AnimalRepository $animalRepository): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $roles = $currentUser->getRoles();
        $isVet = in_array('ROLE_VETO', $roles, true);
        $isSecretary = in_array('ROLE_SECRETARY', $roles, true) || in_array('ROLE_SECRETAIRE', $roles, true);

        if (!$isVet && !$isSecretary) {
            throw $this->createAccessDeniedException();
        }

        $search = trim((string) $request->query->get('q', ''));
        if ($isVet) {
            $animals = $animalRepository->findByVetWithSearch($currentUser, $search);
        } else {
            $animals = $animalRepository->findBySecretaryCabinetWithSearch($currentUser, $search);
        }

        return $this->render('patients/index.html.twig', [
            'animals' => $animals,
            'search' => $search,
            'isReadOnly' => $isSecretary,
        ]);
    }

    #[Route('/nouveau-dossier', name: 'app_vet_patients_new_dossier', methods: ['POST'])]
    public function newDossier(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $roles = $currentUser->getRoles();
        $isVet = in_array('ROLE_VETO', $roles, true);
        $isSecretary = in_array('ROLE_SECRETARY', $roles, true) || in_array('ROLE_SECRETAIRE', $roles, true);

        if (!$isVet && !$isSecretary) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('new_dossier', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_vet_patients');
        }

        $email = trim((string) $request->request->get('owner_email'));
        $owner = $userRepository->findOneBy(['email' => $email]);

        if (!$owner) {
            $owner = new User();
            $owner->setEmail($email);
            $owner->setNom(trim((string) $request->request->get('owner_nom')));
            $owner->setPrenom(trim((string) $request->request->get('owner_prenom')));
            $owner->setTelephone(trim((string) $request->request->get('owner_telephone')));
            $owner->setRoles(['ROLE_USER']);
            $owner->setPassword($passwordHasher->hashPassword($owner, bin2hex(random_bytes(10))));
            
            $baseSlug = mb_strtolower($owner->getPrenom() . '-' . $owner->getNom());
            $baseSlug = preg_replace('/[^a-z0-9]+/', '-', $baseSlug);
            $baseSlug = trim($baseSlug, '-');
            $slug = $baseSlug;
            $counter = 1;
            while ($userRepository->findOneBy(['slug' => $slug])) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            $owner->setSlug($slug);
            
            $entityManager->persist($owner);
        }

        $existingAnimalId = $request->request->get('existing_animal_id');
        $animal = null;
        if ($existingAnimalId) {
            $animal = $entityManager->getRepository(Animal::class)->find($existingAnimalId);
        }
        
        $isNewAnimal = false;
        if (!$animal) {
            $animal = new Animal();
            $isNewAnimal = true;
        }

        $animal->setProprietaire($owner);
        $animal->setNom(trim((string) $request->request->get('animal_nom')));
        $animal->setEspece(trim((string) $request->request->get('animal_espece')));
        $animal->setRace(trim((string) $request->request->get('animal_race')));
        $animal->setPoids(trim((string) $request->request->get('animal_poids')));
        
        $dateNaissanceStr = trim((string) $request->request->get('animal_naissance'));
        if ($dateNaissanceStr) {
            $animal->setAge($dateNaissanceStr);
        }

        if ($isNewAnimal) {
            $baseSlug = mb_strtolower($animal->getNom());
            $baseSlug = preg_replace('/[^a-z0-9]+/', '-', $baseSlug);
            $baseSlug = trim($baseSlug, '-');
            $slug = $baseSlug;
            $counter = 1;
            while ($entityManager->getRepository(Animal::class)->findOneBy(['slug' => $slug])) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            $animal->setSlug($slug);
            $entityManager->persist($animal);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Dossier patient enregistré avec succès.');
        return $this->redirectToRoute('app_vet_patients');
    }

    #[Route('/api/search-all', name: 'app_vet_patients_search_all', methods: ['GET'])]
    public function searchAll(Request $request, AnimalRepository $animalRepository): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json([], Response::HTTP_UNAUTHORIZED);
        }

        $q = trim((string) $request->query->get('q', ''));
        if (strlen($q) < 2) {
            return $this->json([]);
        }

        $qb = $animalRepository->createQueryBuilder('a')
            ->leftJoin('a.proprietaire', 'p')
            ->addSelect('p')
            ->where('a.nom LIKE :q')
            ->orWhere('p.email LIKE :q')
            ->orWhere('p.nom LIKE :q')
            ->orWhere('p.prenom LIKE :q')
            ->setParameter('q', '%' . $q . '%')
            ->setMaxResults(10);

        $animals = $qb->getQuery()->getResult();
        $results = [];
        foreach ($animals as $a) {
            $owner = $a->getProprietaire();
            $results[] = [
                'id' => $a->getId(),
                'animal_nom' => $a->getNom(),
                'animal_espece' => $a->getEspece(),
                'animal_race' => $a->getRace(),
                'animal_naissance' => $a->getAge(),
                'animal_poids' => $a->getPoids(),
                'owner_nom' => $owner ? $owner->getNom() : '',
                'owner_prenom' => $owner ? $owner->getPrenom() : '',
                'owner_email' => $owner ? $owner->getEmail() : '',
                'owner_telephone' => $owner ? $owner->getTelephone() : '',
            ];
        }

        return $this->json($results);
    }

    #[Route('/{slug}', name: 'app_vet_patients_show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] Animal $animal,
        RendezVousRepository $rendezVousRepository,
        TraitementRepository $traitementRepository,
    ): Response {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $roles = $currentUser->getRoles();
        $isVet = in_array('ROLE_VETO', $roles, true);
        $isSecretary = in_array('ROLE_SECRETARY', $roles, true) || in_array('ROLE_SECRETAIRE', $roles, true);

        if (!$isVet && !$isSecretary) {
            throw $this->createAccessDeniedException();
        }

        if ($isVet && !$rendezVousRepository->vetHasRdvWithAnimal($currentUser, $animal)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès au carnet de cet animal.');
        }

        if ($isSecretary && !$rendezVousRepository->secretaryHasAccessToAnimal($currentUser, $animal)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès au carnet de cet animal.');
        }

        $rdvHistory = $rendezVousRepository->findByAnimalForVet($animal);
        $traitements = $traitementRepository->findByAnimal($animal);
        $lastVisit = !empty($rdvHistory) ? $rdvHistory[0] : null;

        return $this->render('patients/show.html.twig', [
            'animal' => $animal,
            'rdvHistory' => $rdvHistory,
            'traitements' => $traitements,
            'lastVisit' => $lastVisit,
            'vet' => $isVet ? $currentUser : null,
            'isReadOnly' => $isSecretary,
        ]);
    }

    #[Route('/{slug}/update', name: 'app_vet_patients_update_animal', methods: ['POST'])]
    public function updateAnimal(
        #[MapEntity(mapping: ['slug' => 'slug'])] Animal $animal,
        Request $request,
        EntityManagerInterface $entityManager,
        RendezVousRepository $rendezVousRepository,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        /** @var User $vet */
        $vet = $this->getUser();

        if (!$rendezVousRepository->vetHasRdvWithAnimal($vet, $animal)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('update_animal_' . $animal->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_vet_patients_show', ['slug' => $animal->getSlug()]);
        }

        $poids = trim((string) $request->request->get('poids', ''));
        $race = trim((string) $request->request->get('race', ''));
        $age = trim((string) $request->request->get('age', ''));
        $espece = trim((string) $request->request->get('espece', ''));
        $vaccinAJour = $request->request->get('vaccinAJour') === '1';
        $prochainVaccinStr = trim((string) $request->request->get('prochainVaccin', ''));

        if ($poids !== '') {
            $animal->setPoids($poids);
        }
        if ($race !== '') {
            $animal->setRace($race);
        }
        if ($age !== '') {
            $animal->setAge($age);
        }
        if ($espece !== '') {
            $animal->setEspece($espece);
        }

        $animal->setVaccinAJour($vaccinAJour);

        if ($prochainVaccinStr !== '') {
            try {
                $animal->setProchainVaccin(new \DateTime($prochainVaccinStr));
            } catch (\Exception) {
            }
        } else {
            $animal->setProchainVaccin(null);
        }

        $entityManager->flush();
        $this->addFlash('success', 'Fiche animal mise à jour.');

        return $this->redirectToRoute('app_vet_patients_show', ['slug' => $animal->getSlug()]);
    }

    #[Route('/rdv/{slug}/remarque', name: 'app_vet_patients_update_remarque', methods: ['POST'])]
    public function updateRemarque(
        string $slug,
        Request $request,
        RendezVousRepository $rendezVousRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        /** @var User $vet */
        $vet = $this->getUser();

        $rdv = $rendezVousRepository->findOneBy(['slug' => $slug]);
        if (!$rdv) {
            throw $this->createNotFoundException();
        }

        if ($rdv->getVeterinaire() !== $vet) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres rendez-vous.');
        }

        if (!$this->isCsrfTokenValid('remarque_' . $rdv->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_vet_patients_show', ['slug' => $rdv->getAnimal()?->getSlug()]);
        }

        $remarque = trim((string) $request->request->get('remarque', ''));
        $rdv->setRemarque($remarque !== '' ? $remarque : null);
        $entityManager->flush();

        $this->addFlash('success', 'Compte-rendu mis à jour.');
        return $this->redirectToRoute('app_vet_patients_show', ['slug' => $rdv->getAnimal()?->getSlug()]);
    }

    #[Route('/{slug}/traitement/new', name: 'app_vet_patients_traitement_new', methods: ['POST'])]
    public function addTraitement(
        #[MapEntity(mapping: ['slug' => 'slug'])] Animal $animal,
        Request $request,
        EntityManagerInterface $entityManager,
        RendezVousRepository $rendezVousRepository,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        /** @var User $vet */
        $vet = $this->getUser();

        if (!$rendezVousRepository->vetHasRdvWithAnimal($vet, $animal)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('traitement_new_' . $animal->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_vet_patients_show', ['slug' => $animal->getSlug()]);
        }

        $libelle = trim((string) $request->request->get('libelle', ''));
        $posologie = trim((string) $request->request->get('posologie', ''));
        $dateDebutStr = trim((string) $request->request->get('dateDebut', ''));
        $dateFinStr = trim((string) $request->request->get('dateFin', ''));

        if ($libelle === '' || $dateDebutStr === '') {
            $this->addFlash('danger', 'Le libellé et la date de début sont obligatoires.');
            return $this->redirectToRoute('app_vet_patients_show', ['slug' => $animal->getSlug()]);
        }

        try {
            $dateDebut = new \DateTime($dateDebutStr);
        } catch (\Exception) {
            $this->addFlash('danger', 'Date de début invalide.');
            return $this->redirectToRoute('app_vet_patients_show', ['slug' => $animal->getSlug()]);
        }

        $traitement = new Traitement();
        $traitement->setAnimal($animal);
        $traitement->setVeterinaire($vet);
        $traitement->setLibelle($libelle);
        $traitement->setPosologie($posologie !== '' ? $posologie : null);
        $traitement->setDateDebut($dateDebut);

        if ($dateFinStr !== '') {
            try {
                $traitement->setDateFin(new \DateTime($dateFinStr));
            } catch (\Exception) {
            }
        }

        $entityManager->persist($traitement);
        $entityManager->flush();

        $this->addFlash('success', 'Traitement ajouté.');
        return $this->redirectToRoute('app_vet_patients_show', ['slug' => $animal->getSlug()]);
    }

    #[Route('/traitement/{slug}/delete', name: 'app_vet_patients_traitement_delete', methods: ['POST'])]
    public function deleteTraitement(
        string $slug,
        Request $request,
        TraitementRepository $traitementRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        /** @var User $vet */
        $vet = $this->getUser();

        $traitement = $traitementRepository->findOneBy(['slug' => $slug]);
        if (!$traitement) {
            throw $this->createNotFoundException();
        }

        if ($traitement->getVeterinaire() !== $vet) {
            throw $this->createAccessDeniedException('Vous ne pouvez supprimer que vos propres traitements.');
        }

        if (!$this->isCsrfTokenValid('delete_traitement_' . $traitement->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_vet_patients_show', ['slug' => $traitement->getAnimal()?->getSlug()]);
        }

        $animalSlug = $traitement->getAnimal()?->getSlug();
        $entityManager->remove($traitement);
        $entityManager->flush();

        $this->addFlash('success', 'Traitement supprimé.');
        return $this->redirectToRoute('app_vet_patients_show', ['slug' => $animalSlug]);
    }
}
