<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\DayOfWorkRepository;
use App\Repository\UserRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;

final class SearchVetController extends AbstractController
{
    #[Route('/front/search', name: 'app_search_vet')]
    public function index(Request $request, UserRepository $userRepository, DayOfWorkRepository $dayOfWorkRepository,EntityManagerInterface $em, PaginatorInterface $paginator): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $lat = $request->query->get('lat');
        $lon = $request->query->get('lon');
        $distance = $request->query->getInt('distance', 20); // Default 20 km

        // Ensure positive values
        $page = max(1, $page);
        $limit = max(1, $limit);

        // Convert lat/lon to float if present
        $lat = $lat !== null && $lat !== '' ? (float) $lat : null;
        $lon = $lon !== null && $lon !== '' ? (float) $lon : null;

        $allVets = $em->getRepository(User::class)->findall();
        $allVets = array_filter($allVets, function ($user) {
            return in_array('ROLE_VETO', $user->getRoles());
        });
        // dd($allVets);
        $pagination = $paginator->paginate(
            $allVets,
            $request->query->getInt('page', 1),
            10
        );
        // dd($allVets);
        // $vetsPaginator = $userRepository->findAllVets($page, $limit, $lat, $lon, $distance);
        // $totalItems = count($vetsPaginator);
        // $totalPages = ceil($totalItems / $limit);

        // $vets = iterator_to_array($vetsPaginator->getIterator());
        $vetAvailability = $this->buildVetAvailability($allVets, $dayOfWorkRepository);
        return $this->render('search-vet/searchvet.html.twig', [
            'allVets' => $allVets,
            'pagination' => $pagination,
            'vetAvailability' => $vetAvailability,
            'currentPage' => $page,
            'limit' => $limit,
            // 'totalPages' => $totalPages,
            // 'totalItems' => $totalItems,
            'lat' => $lat,
            'lon' => $lon,
            'distance' => $distance,
            'location' => $request->query->get('location'),
        ]);
    }

    /**
     * @param User[] $vets
     * @return array<int, array{isOpen: bool, label: string, detail: string}>
     */
    private function buildVetAvailability(array $vets, DayOfWorkRepository $dayOfWorkRepository): array
    {
        $availability = [];

        if ($vets === []) {
            return $availability;
        }

        $vetIds = [];
        foreach ($vets as $vet) {
            $vetId = $vet->getId();
            if ($vetId === null) {
                continue;
            }

            $vetIds[] = $vetId;
            $availability[$vetId] = [
                'isOpen' => false,
                'label' => 'Fermé',
                'detail' => 'Aucun horaire renseigné',
            ];
        }

        if ($vetIds === []) {
            return $availability;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $todayOrder = (int) $now->format('N');
        $currentTime = $now->format('H:i:s');

        $schedules = $dayOfWorkRepository->createQueryBuilder('d')
            ->innerJoin('d.user', 'u')->addSelect('u')
            ->innerJoin('d.jour', 'j')->addSelect('j')
            ->leftJoin('d.horaires', 'h')->addSelect('h')
            ->andWhere('u.id IN (:ids)')
            ->setParameter('ids', $vetIds)
            ->orderBy('u.id', 'ASC')
            ->addOrderBy('j.ordre', 'ASC')
            ->getQuery()
            ->getResult();

        $schedulesByVet = [];
        foreach ($schedules as $dayOfWork) {
            $vetId = $dayOfWork->getUser()?->getId();
            $dayOrder = $dayOfWork->getJour()?->getOrdre();

            if ($vetId === null || $dayOrder === null) {
                continue;
            }

            $schedulesByVet[$vetId][$dayOrder] = $dayOfWork;
        }

        foreach (array_keys($availability) as $vetId) {
            $vetDays = $schedulesByVet[$vetId] ?? [];
            $todayDay = $vetDays[$todayOrder] ?? null;

            if ($todayDay !== null && $todayDay->isWorking()) {
                $todaySlots = $this->extractSlots($todayDay);

                foreach ($todaySlots as $slot) {
                    if ($currentTime >= $slot['start'] && $currentTime <= $slot['end']) {
                        $availability[$vetId] = [
                            'isOpen' => true,
                            'label' => 'Ouvert',
                            'detail' => sprintf('Ferme à %s', substr($slot['end'], 0, 5)),
                        ];
                        continue 2;
                    }
                }

                foreach ($todaySlots as $slot) {
                    if ($currentTime < $slot['start']) {
                        $availability[$vetId] = [
                            'isOpen' => false,
                            'label' => 'Fermé',
                            'detail' => sprintf("Ouvre aujourd'hui à %s", substr($slot['start'], 0, 5)),
                        ];
                        continue 2;
                    }
                }
            }

            for ($offset = 1; $offset <= 7; $offset++) {
                $nextDayOrder = (($todayOrder - 1 + $offset) % 7) + 1;
                $nextDay = $vetDays[$nextDayOrder] ?? null;

                if ($nextDay === null || !$nextDay->isWorking()) {
                    continue;
                }

                $nextSlots = $this->extractSlots($nextDay);
                if ($nextSlots === []) {
                    continue;
                }

                $firstSlot = $nextSlots[0];
                $dayName = $nextDay->getJour()?->getLibelle() ?? 'ce jour';

                $availability[$vetId] = [
                    'isOpen' => false,
                    'label' => 'Fermé',
                    'detail' => sprintf('Ouvre %s à %s', $dayName, substr($firstSlot['start'], 0, 5)),
                ];

                continue 2;
            }

            $availability[$vetId] = [
                'isOpen' => false,
                'label' => 'Fermé',
                'detail' => 'Aucun horaire renseigné',
            ];
        }

        return $availability;
    }

    /**
     * @return array<int, array{start: string, end: string}>
     */
    private function extractSlots($dayOfWork): array
    {
        $slots = [];

        foreach ($dayOfWork->getHoraires() as $horaire) {
            if ($horaire->getMorningStart() !== null && $horaire->getMorningEnd() !== null) {
                $slots[] = [
                    'start' => $horaire->getMorningStart()->format('H:i:s'),
                    'end' => $horaire->getMorningEnd()->format('H:i:s'),
                ];
            }

            if ($horaire->getAfternoonStart() !== null && $horaire->getAfternoonEnd() !== null) {
                $slots[] = [
                    'start' => $horaire->getAfternoonStart()->format('H:i:s'),
                    'end' => $horaire->getAfternoonEnd()->format('H:i:s'),
                ];
            }
        }

        usort($slots, static fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $slots;
    }
}
