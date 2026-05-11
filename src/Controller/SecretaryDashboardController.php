<?php

namespace App\Controller;

use App\Entity\CabinetUser;
use App\Entity\User;
use App\Repository\CabinetUserRepository;
use App\Repository\RendezVousRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SecretaryDashboardController extends AbstractController
{
    #[Route('/app/secretaire/tableau-de-bord', name: 'app_secretary_dashboard', methods: ['GET'])]
    public function index(
        RendezVousRepository $rendezVousRepository,
        CabinetUserRepository $cabinetUserRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isGranted('ROLE_SECRETARY')) {
            return $this->redirectToRoute('app_home');
        }

        $cabinet = $cabinetUserRepository->findCabinetForUserRole($user, CabinetUser::ROLE_SECRETAIRE);
        if ($cabinet === null) {
            return $this->render('secretary_dashboard/index.html.twig', [
                'cabinet' => null,
                'nextAppointments' => [],
                'todayAppointments' => [],
                'recentVisits' => [],
                'followUpVisits' => [],
                'todayCount' => 0,
                'upcomingCount' => 0,
                'patientsCount' => 0,
                'cancellationRate' => 0,
                'cancelledRecent' => 0,
                'totalRecent' => 0,
                'cabinetUsers' => [],
                'vets' => [],
            ]);
        }

        $now = new \DateTime();
        $startOfDay = (clone $now)->setTime(0, 0, 0);
        $endOfDay = (clone $now)->setTime(23, 59, 59);

        $nextAppointments = $rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.animal', 'a')->addSelect('a')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin(CabinetUser::class, 'cuVet', 'WITH', 'cuVet.user = r.veterinaire AND cuVet.roleInCabinet = :vetRole')
            ->andWhere('cuVet.cabinet = :cabinet')
            ->andWhere('r.dateHeure >= :now')
            ->andWhere('r.statut != :annule')
            ->setParameter('cabinet', $cabinet)
            ->setParameter('vetRole', CabinetUser::ROLE_VETERINAIRE)
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
            ->leftJoin(CabinetUser::class, 'cuVet', 'WITH', 'cuVet.user = r.veterinaire AND cuVet.roleInCabinet = :vetRole')
            ->andWhere('cuVet.cabinet = :cabinet')
            ->andWhere('r.dateHeure BETWEEN :startDay AND :endDay')
            ->andWhere('r.statut != :annule')
            ->setParameter('cabinet', $cabinet)
            ->setParameter('vetRole', CabinetUser::ROLE_VETERINAIRE)
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
            ->leftJoin(CabinetUser::class, 'cuVet', 'WITH', 'cuVet.user = r.veterinaire AND cuVet.roleInCabinet = :vetRole')
            ->andWhere('cuVet.cabinet = :cabinet')
            ->andWhere('r.dateHeure < :now')
            ->andWhere('r.statut != :annule')
            ->setParameter('cabinet', $cabinet)
            ->setParameter('vetRole', CabinetUser::ROLE_VETERINAIRE)
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

        $todayCount = (int) $rendezVousRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->leftJoin(CabinetUser::class, 'cuVet', 'WITH', 'cuVet.user = r.veterinaire AND cuVet.roleInCabinet = :vetRole')
            ->andWhere('cuVet.cabinet = :cabinet')
            ->andWhere('r.dateHeure BETWEEN :startDay AND :endDay')
            ->andWhere('r.statut != :annule')
            ->setParameter('cabinet', $cabinet)
            ->setParameter('vetRole', CabinetUser::ROLE_VETERINAIRE)
            ->setParameter('startDay', $startOfDay)
            ->setParameter('endDay', $endOfDay)
            ->setParameter('annule', 'annule')
            ->getQuery()
            ->getSingleScalarResult();

        $upcomingCount = (int) $rendezVousRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->leftJoin(CabinetUser::class, 'cuVet', 'WITH', 'cuVet.user = r.veterinaire AND cuVet.roleInCabinet = :vetRole')
            ->andWhere('cuVet.cabinet = :cabinet')
            ->andWhere('r.dateHeure >= :now')
            ->andWhere('r.statut != :annule')
            ->setParameter('cabinet', $cabinet)
            ->setParameter('vetRole', CabinetUser::ROLE_VETERINAIRE)
            ->setParameter('now', $now)
            ->setParameter('annule', 'annule')
            ->getQuery()
            ->getSingleScalarResult();

        $patientsCount = (int) $rendezVousRepository->createQueryBuilder('r')
            ->select('COUNT(DISTINCT a.id)')
            ->leftJoin('r.animal', 'a')
            ->leftJoin(CabinetUser::class, 'cuVet', 'WITH', 'cuVet.user = r.veterinaire AND cuVet.roleInCabinet = :vetRole')
            ->andWhere('cuVet.cabinet = :cabinet')
            ->setParameter('cabinet', $cabinet)
            ->setParameter('vetRole', CabinetUser::ROLE_VETERINAIRE)
            ->getQuery()
            ->getSingleScalarResult();

        $since = (clone $now)->modify('-30 days');
        $totalRecent = (int) $rendezVousRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->leftJoin(CabinetUser::class, 'cuVet', 'WITH', 'cuVet.user = r.veterinaire AND cuVet.roleInCabinet = :vetRole')
            ->andWhere('cuVet.cabinet = :cabinet')
            ->andWhere('r.dateHeure >= :since')
            ->setParameter('cabinet', $cabinet)
            ->setParameter('vetRole', CabinetUser::ROLE_VETERINAIRE)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        $cancelledRecent = (int) $rendezVousRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->leftJoin(CabinetUser::class, 'cuVet', 'WITH', 'cuVet.user = r.veterinaire AND cuVet.roleInCabinet = :vetRole')
            ->andWhere('cuVet.cabinet = :cabinet')
            ->andWhere('r.dateHeure >= :since')
            ->andWhere('r.statut = :annule')
            ->setParameter('cabinet', $cabinet)
            ->setParameter('vetRole', CabinetUser::ROLE_VETERINAIRE)
            ->setParameter('since', $since)
            ->setParameter('annule', 'annule')
            ->getQuery()
            ->getSingleScalarResult();

        $cancellationRate = $totalRecent > 0 ? (int) round(($cancelledRecent / $totalRecent) * 100) : 0;

        $vets = $cabinetUserRepository->findUsersByCabinetRole($cabinet, CabinetUser::ROLE_VETERINAIRE);
        $cabinetUsers = $cabinetUserRepository->findByCabinetOrdered($cabinet);

        return $this->render('secretary_dashboard/index.html.twig', [
            'cabinet' => $cabinet,
            'nextAppointments' => $nextAppointments,
            'todayAppointments' => $todayAppointments,
            'recentVisits' => $recentVisits,
            'followUpVisits' => $followUpVisits,
            'todayCount' => $todayCount,
            'upcomingCount' => $upcomingCount,
            'patientsCount' => $patientsCount,
            'cancellationRate' => $cancellationRate,
            'cancelledRecent' => $cancelledRecent,
            'totalRecent' => $totalRecent,
            'cabinetUsers' => $cabinetUsers,
            'vets' => $vets,
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