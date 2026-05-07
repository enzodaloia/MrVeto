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
    #[Route('/app/carnet-sante', name: 'app_carnet_sante', methods: ['GET'])]
    public function index(
        Request $request,
        AnimalRepository $animalRepository,
        RendezVousRepository $rendezVousRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isGranted('ROLE_SECRETARY')) {
            return $this->redirectToRoute('app_home');
        }

        $animals = $animalRepository->createQueryBuilder('a')
            ->andWhere('a.proprietaire = :owner')
            ->setParameter('owner', $user)
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var Animal|null $selectedAnimal */
        $selectedAnimal = null;
        $selectedAnimalId = $request->query->getInt('animal');

        if ($selectedAnimalId > 0) {
            foreach ($animals as $animal) {
                if ($animal->getId() === $selectedAnimalId) {
                    $selectedAnimal = $animal;
                    break;
                }
            }
        }

        if ($selectedAnimal === null && !empty($animals)) {
            $selectedAnimal = $animals[0];
        }

        $medicalHistory = [];
        if ($selectedAnimal !== null) {
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

    #[Route('/app/carnet-sante/save-animal', name: 'app_carnet_sante_save_animal', methods: ['POST'])]
    public function saveAnimal(Request $request, EntityManagerInterface $entityManager): Response
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

        if ($animalNom === '' || $animalEspece === '' || $animalAge === '') {
            $this->addFlash('danger', 'Nom, espèce et âge sont obligatoires.');

            return $this->redirectToRoute('app_carnet_sante', [
                'animal' => $request->request->getInt('_selectedAnimal'),
            ]);
        }

        $animal->setNom($animalNom);
        $animal->setEspece($animalEspece);
        $animal->setRace($request->request->get('animalRace'));
        $animal->setPoids($request->request->get('animalPoids'));
        $animal->setVaccinAJour($request->request->get('vaccin') === 'oui');
        $animal->setAge($animalAge);

        $entityManager->flush();

        $this->addFlash('success', 'Animal créé avec succès !');

        return $this->redirectToRoute('app_carnet_sante', ['animal' => $animal->getId()]);
    }
}