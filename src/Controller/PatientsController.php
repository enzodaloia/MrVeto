<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Traitement;
use App\Entity\User;
use App\Repository\AnimalRepository;
use App\Repository\RendezVousRepository;
use App\Repository\TraitementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vet/patients')]
final class PatientsController extends AbstractController
{
    #[Route('', name: 'app_vet_patients', methods: ['GET'])]
    public function index(Request $request, AnimalRepository $animalRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        /** @var User $vet */
        $vet = $this->getUser();
        $search = trim((string) $request->query->get('q', ''));
        $animals = $animalRepository->findByVetWithSearch($vet, $search);

        return $this->render('patients/index.html.twig', [
            'animals' => $animals,
            'search' => $search,
        ]);
    }

    #[Route('/{id}', name: 'app_vet_patients_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        Animal $animal,
        RendezVousRepository $rendezVousRepository,
        TraitementRepository $traitementRepository,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        /** @var User $vet */
        $vet = $this->getUser();

        if (!$rendezVousRepository->vetHasRdvWithAnimal($vet, $animal)) {
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
            'vet' => $vet,
        ]);
    }

    #[Route('/{id}/update', name: 'app_vet_patients_update_animal', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateAnimal(
        Animal $animal,
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
            return $this->redirectToRoute('app_vet_patients_show', ['id' => $animal->getId()]);
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

        return $this->redirectToRoute('app_vet_patients_show', ['id' => $animal->getId()]);
    }

    #[Route('/rdv/{rdvId}/remarque', name: 'app_vet_patients_update_remarque', methods: ['POST'], requirements: ['rdvId' => '\d+'])]
    public function updateRemarque(
        int $rdvId,
        Request $request,
        RendezVousRepository $rendezVousRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        /** @var User $vet */
        $vet = $this->getUser();

        $rdv = $rendezVousRepository->find($rdvId);
        if (!$rdv) {
            throw $this->createNotFoundException();
        }

        if ($rdv->getVeterinaire() !== $vet) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres rendez-vous.');
        }

        if (!$this->isCsrfTokenValid('remarque_' . $rdvId, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_vet_patients_show', ['id' => $rdv->getAnimal()?->getId()]);
        }

        $remarque = trim((string) $request->request->get('remarque', ''));
        $rdv->setRemarque($remarque !== '' ? $remarque : null);
        $entityManager->flush();

        $this->addFlash('success', 'Compte-rendu mis à jour.');
        return $this->redirectToRoute('app_vet_patients_show', ['id' => $rdv->getAnimal()?->getId()]);
    }

    #[Route('/{id}/traitement/new', name: 'app_vet_patients_traitement_new', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addTraitement(
        Animal $animal,
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
            return $this->redirectToRoute('app_vet_patients_show', ['id' => $animal->getId()]);
        }

        $libelle = trim((string) $request->request->get('libelle', ''));
        $posologie = trim((string) $request->request->get('posologie', ''));
        $dateDebutStr = trim((string) $request->request->get('dateDebut', ''));
        $dateFinStr = trim((string) $request->request->get('dateFin', ''));

        if ($libelle === '' || $dateDebutStr === '') {
            $this->addFlash('danger', 'Le libellé et la date de début sont obligatoires.');
            return $this->redirectToRoute('app_vet_patients_show', ['id' => $animal->getId()]);
        }

        try {
            $dateDebut = new \DateTime($dateDebutStr);
        } catch (\Exception) {
            $this->addFlash('danger', 'Date de début invalide.');
            return $this->redirectToRoute('app_vet_patients_show', ['id' => $animal->getId()]);
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
        return $this->redirectToRoute('app_vet_patients_show', ['id' => $animal->getId()]);
    }

    #[Route('/traitement/{traitementId}/delete', name: 'app_vet_patients_traitement_delete', methods: ['POST'], requirements: ['traitementId' => '\d+'])]
    public function deleteTraitement(
        int $traitementId,
        Request $request,
        TraitementRepository $traitementRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        /** @var User $vet */
        $vet = $this->getUser();

        $traitement = $traitementRepository->find($traitementId);
        if (!$traitement) {
            throw $this->createNotFoundException();
        }

        if ($traitement->getVeterinaire() !== $vet) {
            throw $this->createAccessDeniedException('Vous ne pouvez supprimer que vos propres traitements.');
        }

        if (!$this->isCsrfTokenValid('delete_traitement_' . $traitementId, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_vet_patients_show', ['id' => $traitement->getAnimal()?->getId()]);
        }

        $animalId = $traitement->getAnimal()?->getId();
        $entityManager->remove($traitement);
        $entityManager->flush();

        $this->addFlash('success', 'Traitement supprimé.');
        return $this->redirectToRoute('app_vet_patients_show', ['id' => $animalId]);
    }
}
