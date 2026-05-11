<?php

namespace App\Controller;

use App\Entity\CabinetUser;
use App\Entity\User;
use App\Repository\CabinetUserRepository;
use App\Repository\RendezVousRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VetDashboardController extends AbstractController
{
    #[Route('/app/veterinaire/tableau-de-bord', name: 'app_vet_dashboard', methods: ['GET'])]
    public function index(
        RendezVousRepository $rendezVousRepository,
        CabinetUserRepository $cabinetUserRepository,
    ): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isGranted('ROLE_VETO')) {
            return $this->redirectToRoute('app_home');
        }

        $now = new \DateTime();
        $startOfDay = (clone $now)->setTime(0, 0, 0);
        $endOfDay = (clone $now)->setTime(23, 59, 59);

        $nextAppointments = $rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.animal', 'a')->addSelect('a')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->andWhere('r.veterinaire = :vet')
            ->andWhere('r.dateHeure >= :now')
            ->andWhere('r.statut != :annule')
            ->setParameter('vet', $user)
            ->setParameter('now', $now)
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'ASC')
            ->setMaxResults(2)
            ->getQuery()
            ->getResult();

        $nextAppointments = $this->uniqueRendezVous($nextAppointments);

        $todayAppointments = $rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.animal', 'a')->addSelect('a')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->andWhere('r.veterinaire = :vet')
            ->andWhere('r.dateHeure BETWEEN :startDay AND :endDay')
            ->andWhere('r.statut != :annule')
            ->setParameter('vet', $user)
            ->setParameter('startDay', $startOfDay)
            ->setParameter('endDay', $endOfDay)
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'ASC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        $todayAppointments = $this->uniqueRendezVous($todayAppointments);

        $recentVisits = $rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.animal', 'a')->addSelect('a')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->andWhere('r.veterinaire = :vet')
            ->andWhere('r.dateHeure < :now')
            ->andWhere('r.statut != :annule')
            ->setParameter('vet', $user)
            ->setParameter('now', $now)
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        $recentVisits = $this->uniqueRendezVous($recentVisits);

        $followUpVisits = array_values(array_filter(
            $recentVisits,
            static fn ($visit) => $visit->getRemarque() !== null && $visit->getRemarque() !== ''
        ));

        if ($followUpVisits === []) {
            $followUpVisits = $recentVisits;
        }

        $seenAnimals = [];
        $uniqueFollowUps = [];
        foreach ($followUpVisits as $visit) {
            $animal = $visit->getAnimal();
            $animalId = $animal?->getId();
            if ($animalId === null || isset($seenAnimals[$animalId])) {
                continue;
            }
            $seenAnimals[$animalId] = true;
            $uniqueFollowUps[] = $visit;
        }

        $followUpVisits = $uniqueFollowUps;

        $since = (clone $now)->modify('-30 days');
        $totalRecent = (int) $rendezVousRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.veterinaire = :vet')
            ->andWhere('r.dateHeure >= :since')
            ->setParameter('vet', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        $cancelledRecent = (int) $rendezVousRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.veterinaire = :vet')
            ->andWhere('r.dateHeure >= :since')
            ->andWhere('r.statut = :annule')
            ->setParameter('vet', $user)
            ->setParameter('since', $since)
            ->setParameter('annule', 'annule')
            ->getQuery()
            ->getSingleScalarResult();

        $cancellationRate = $totalRecent > 0 ? (int) round(($cancelledRecent / $totalRecent) * 100) : 0;

        $cabinetUsers = [];
        $cabinet = $cabinetUserRepository->findCabinetForUserRole($user, CabinetUser::ROLE_VETERINAIRE);
        if ($cabinet !== null) {
            $cabinetUsers = $cabinetUserRepository->findByCabinetOrdered($cabinet);
        }

        return $this->render('vet_dashboard/index.html.twig', [
            'nextAppointments' => $nextAppointments,
            'todayAppointments' => $todayAppointments,
            'recentVisits' => $recentVisits,
            'followUpVisits' => $followUpVisits,
            'cancellationRate' => $cancellationRate,
            'cancelledRecent' => $cancelledRecent,
            'totalRecent' => $totalRecent,
            'cabinetUsers' => $cabinetUsers,
        ]);
    }

    /**
     * @param list<object> $appointments
     * @return list<object>
     */
    private function uniqueRendezVous(array $appointments): array
    {
        $seen = [];
        $unique = [];
        foreach ($appointments as $appointment) {
            $id = $appointment->getId();
            if ($id === null || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $unique[] = $appointment;
        }

        return $unique;
    }
}
