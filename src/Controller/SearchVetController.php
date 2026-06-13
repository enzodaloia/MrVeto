<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\DayOfWorkRepository;
use App\Repository\SpecialiteRepository;
use App\Repository\UserRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;

final class SearchVetController extends AbstractController
{
    #[Route('/recherche', name: 'app_search_vet')]
    public function index(Request $request, UserRepository $userRepository, DayOfWorkRepository $dayOfWorkRepository, PaginatorInterface $paginator, SpecialiteRepository $specialiteRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, $request->query->getInt('limit', 10));

        $lat = $request->query->get('lat');
        $lon = $request->query->get('lon');
        $distance = $request->query->getInt('distance', 20);

        $lat = ($lat !== null && $lat !== '') ? (float) $lat : null;
        $lon = ($lon !== null && $lon !== '') ? (float) $lon : null;

        $filterUrgentiste = $request->query->getBoolean('urgentiste');
        $filterSpecialites = array_values(array_filter(array_map('strval', (array) $request->query->all('spec'))));
        $filterAnimaux = array_values(array_filter(array_map('strval', (array) $request->query->all('animaux'))));

        $query = $userRepository->createVetsQueryBuilder(
            $lat,
            $lon,
            $lat !== null ? $distance : null,
            $filterUrgentiste,
            $filterSpecialites,
            $filterAnimaux
        );

        $pagination = $paginator->paginate($query, $page, $limit);

        /** @var User[] $pageVets */
        $pageVets = $pagination->getItems();
        $vetAvailability = $this->buildVetAvailability($pageVets, $dayOfWorkRepository);

        return $this->render('search-vet/searchvet.html.twig', [
            'pagination' => $pagination,
            'vetAvailability' => $vetAvailability,
            'currentPage' => $page,
            'limit' => $limit,
            'lat' => $lat,
            'lon' => $lon,
            'distance' => $distance,
            'location' => $request->query->get('location'),
            'filterUrgentiste' => $filterUrgentiste,
            'filterSpecialites' => $filterSpecialites,
            'filterAnimaux' => $filterAnimaux,
            'allSpecialites' => $specialiteRepository->findAllOrdered(),
        ]);
    }

    #[Route('/urgence/nearest', name: 'app_urgence_nearest')]
    public function nearestUrgentiste(Request $request, UserRepository $userRepository): JsonResponse
    {
        $latParam = $request->query->get('lat');
        $lonParam = $request->query->get('lon');

        if ($latParam === null || $latParam === '' || $lonParam === null || $lonParam === '') {
            return $this->json(['error' => 'Coordonnées manquantes.'], 400);
        }

        $lat = (float) $latParam;
        $lon = (float) $lonParam;

        $vets = $userRepository->findAllUrgentistes();

        if ($vets === []) {
            return $this->json(['error' => 'Aucun vétérinaire urgentiste disponible pour le moment.'], 404);
        }

        // Compute Haversine distance for each vet and pick the nearest
        $nearest = null;
        $nearestDistance = PHP_FLOAT_MAX;

        foreach ($vets as $vet) {
            $vetLat = (float) $vet->getLatitude();
            $vetLon = (float) $vet->getLongitude();

            $dlat = deg2rad($vetLat - $lat);
            $dlon = deg2rad($vetLon - $lon);
            $a = sin($dlat / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($vetLat)) * sin($dlon / 2) ** 2;
            $km = 6371 * 2 * asin(sqrt($a));

            if ($km < $nearestDistance) {
                $nearestDistance = $km;
                $nearest = $vet;
            }
        }

        if ($nearest === null) {
            return $this->json(['error' => 'Aucun vétérinaire urgentiste disponible pour le moment.'], 404);
        }

        return $this->json([
            'nom' => $nearest->getNom(),
            'prenom' => $nearest->getPrenom(),
            'telephone' => $nearest->getTelephone(),
            'adresse' => $nearest->getAdressecabinet(),
            'distance' => round($nearestDistance, 1),
            'slug' => $nearest->getSlug(),
            'profileUrl' => $this->generateUrl('app_vet_info', ['slug' => $nearest->getSlug()]),
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
