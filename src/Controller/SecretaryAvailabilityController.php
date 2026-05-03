<?php

namespace App\Controller;

use App\Entity\CabinetUser;
use App\Entity\DayOfWork;
use App\Entity\Horaire;
use App\Entity\User;
use App\Form\DisponibiliteType;
use App\Repository\CabinetUserRepository;
use App\Repository\DayOfWorkRepository;
use App\Repository\JourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SecretaryAvailabilityController extends AbstractController
{
    #[Route('/secretary/vet/{id}/availability', name: 'app_secretary_vet_availability', methods: ['GET', 'POST'])]
    public function editVetAvailability(
        User $vet,
        Request $request,
        CabinetUserRepository $cabinetUserRepository,
        DayOfWorkRepository $dayOfWorkRepository,
        JourRepository $jourRepository,
        EntityManagerInterface $entityManager
    ): Response {
        //$this->denyAccessUnlessGranted('ROLE_SECRETARY');

        $secretary = $this->getUser();
        if (!$secretary instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!in_array('ROLE_VETO', $vet->getRoles(), true)) {
            throw $this->createNotFoundException('Vétérinaire non trouvé.');
        }

        //if (!$this->canSecretaryManageVet($cabinetUserRepository, $secretary, $vet)) {
        //throw $this->createAccessDeniedException('Vous ne pouvez modifier que les horaires des vétérinaires de votre cabinet.');
        //}

        $this->createMissingDaysOfWork($vet, $dayOfWorkRepository, $jourRepository, $entityManager);

        $daysOfWork = $dayOfWorkRepository->createQueryBuilder('d')
            ->innerJoin('d.jour', 'j')->addSelect('j')
            ->leftJoin('d.horaires', 'h')->addSelect('h')
            ->andWhere('d.user = :vet')
            ->setParameter('vet', $vet)
            ->orderBy('j.ordre', 'ASC')
            ->getQuery()
            ->getResult();

        $horaires = [];
        foreach ($daysOfWork as $dayOfWork) {
            if ($dayOfWork->getHoraires()->isEmpty()) {
                $horaire = new Horaire();
                $dayOfWork->addHoraire($horaire);
                $entityManager->persist($horaire);
            } else {
                $horaire = $dayOfWork->getHoraires()->first();
            }

            if ($horaire->getMorningStart() === null && $horaire->getMorningEnd() === null && $horaire->getId() === null) {
                $this->applyDefaultHours($horaire);
            }
            $horaires[] = $horaire;
        }

        $form = $this->createForm(DisponibiliteType::class, ['horaires' => $horaires], [
            'user' => $vet,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $workingDayIds = array_map('intval', $request->request->all('working_days') ?? []);

            foreach ($daysOfWork as $dayOfWork) {
                $dayOfWork->setIsWorking(in_array($dayOfWork->getId(), $workingDayIds, true));
            }

            $entityManager->flush();

            $this->addFlash('success', 'Horaires du vétérinaire enregistrés.');

            return $this->redirectToRoute('app_secretary_vet_availability', [
                'id' => $vet->getId(),
            ]);
        }

        return $this->render('secretary_availability/index.html.twig', [
            'vet' => $vet,
            'daysOfWork' => $daysOfWork,
            'form' => $form->createView(),
        ]);
    }

    private function canSecretaryManageVet(CabinetUserRepository $cabinetUserRepository, User $secretary, User $vet): bool
    {
        $secretaryCabinet = $cabinetUserRepository->findCabinetForUserRole($secretary, CabinetUser::ROLE_SECRETAIRE);
        $vetCabinet = $cabinetUserRepository->findCabinetForUserRole($vet, CabinetUser::ROLE_VETERINAIRE);

        return $secretaryCabinet !== null
            && $vetCabinet !== null
            && $secretaryCabinet->getId() === $vetCabinet->getId();
    }

    private function createMissingDaysOfWork(
        User $vet,
        DayOfWorkRepository $dayOfWorkRepository,
        JourRepository $jourRepository,
        EntityManagerInterface $entityManager
    ): void {
        $existingDays = $dayOfWorkRepository->createQueryBuilder('d')
            ->innerJoin('d.jour', 'j')->addSelect('j')
            ->andWhere('d.user = :vet')
            ->setParameter('vet', $vet)
            ->getQuery()
            ->getResult();

        $existingDayIds = [];
        foreach ($existingDays as $dayOfWork) {
            $jour = $dayOfWork->getJour();
            if ($jour !== null && $jour->getId() !== null) {
                $existingDayIds[] = $jour->getId();
            }
        }

        foreach ($jourRepository->findBy([], ['ordre' => 'ASC']) as $jour) {
            if (in_array($jour->getId(), $existingDayIds, true)) {
                continue;
            }

            $dayOfWork = new DayOfWork();
            $dayOfWork
                ->setUser($vet)
                ->setJour($jour)
                ->setIsWorking(false);

            $entityManager->persist($dayOfWork);
        }

        $entityManager->flush();
    }

    private function applyDefaultHours(Horaire $horaire): void
    {
        if ($horaire->getMorningStart() === null) {
            $horaire->setMorningStart(new \DateTime('08:00'));
        }

        if ($horaire->getMorningEnd() === null) {
            $horaire->setMorningEnd(new \DateTime('12:00'));
        }
    }
}
