<?php

namespace App\Controller;

use App\Entity\DayOfWork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Repository\DayOfWorkRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VetInfoController extends AbstractController
{
    #[Route('/vet/{id}', name: 'app_vet_info')]
    public function index(User $vet = null, DayOfWorkRepository $dayOfWorkRepository): Response
    {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles())) {
            throw new NotFoundHttpException('Vétérinaire non trouvé.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $dayOfWorks = $dayOfWorkRepository->createQueryBuilder('d')
            ->innerJoin('d.jour', 'j')->addSelect('j')
            ->leftJoin('d.horaires', 'h')->addSelect('h')
            ->andWhere('d.user = :vet')
            ->setParameter('vet', $vet)
            ->orderBy('j.ordre', 'ASC')
            ->getQuery()
            ->getResult();

        $status = $this->buildStatus($dayOfWorks, $now);
        $weekAvailabilityPreview = $this->buildWeekAvailability($dayOfWorks, $now, 0);

        $selectedDayOffset = 0;
        foreach ($weekAvailabilityPreview as $day) {
            if ($day['isAvailable']) {
                $selectedDayOffset = $day['dayOffset'];
                break;
            }
        }

        $weekAvailability = $this->buildWeekAvailability($dayOfWorks, $now, $selectedDayOffset);
        $selectedDate = $now->modify(sprintf('+%d day', $selectedDayOffset));
        $selectedDayOrder = (int) $selectedDate->format('N');
        $todaySlots = $this->findDayHalfHourSlots($dayOfWorks, $selectedDayOrder);

        $dayLabels = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];
        $daySlotsByOffset = [];
        $dayLabelsByOffset = [];
        $dayDisplaysByOffset = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $now->modify(sprintf('+%d day', $offset));
            $dayOrder = (int) $date->format('N');
            $dayLabel = $dayLabels[$dayOrder] ?? strtolower($date->format('l'));

            $daySlotsByOffset[$offset] = $this->findDayHalfHourSlots($dayOfWorks, $dayOrder);
            $dayLabelsByOffset[$offset] = $dayLabel;
            $dayDisplaysByOffset[$offset] = sprintf('%s %s', $dayLabel, $date->format('j'));
        }

        return $this->render('vet-info/vetinfo.html.twig', [
            'vet' => $vet,
            'status' => $status,
            'weekAvailability' => $weekAvailability,
            'todaySlots' => $todaySlots,
            'selectedDayLabel' => $dayLabels[$selectedDayOrder] ?? strtolower($selectedDate->format('l')),
            'selectedDayDisplay' => $dayDisplaysByOffset[$selectedDayOffset] ?? (($dayLabels[$selectedDayOrder] ?? strtolower($selectedDate->format('l'))) . ' ' . $selectedDate->format('j')),
            'daySlotsByOffset' => $daySlotsByOffset,
            'dayLabelsByOffset' => $dayLabelsByOffset,
            'dayDisplaysByOffset' => $dayDisplaysByOffset,
        ]);
    }

    /**
     * @param DayOfWork[] $dayOfWorks
     * @return array{isOpen: bool, label: string, detail: string}
     */
    private function buildStatus(array $dayOfWorks, \DateTimeImmutable $now): array
    {
        $todayOrder = (int) $now->format('N');
        $currentTime = $now->format('H:i:s');
        $byOrder = $this->indexByDayOrder($dayOfWorks);

        $todaySlots = $this->findDaySlots($dayOfWorks, $todayOrder);

        foreach ($todaySlots as $slot) {
            if ($currentTime >= $slot['start'] && $currentTime <= $slot['end']) {
                return [
                    'isOpen' => true,
                    'label' => 'Ouvert',
                    'detail' => sprintf('Ferme à %s', $slot['end']),
                ];
            }
        }

        foreach ($todaySlots as $slot) {
            if ($currentTime < $slot['start']) {
                return [
                    'isOpen' => false,
                    'label' => 'Fermé',
                    'detail' => sprintf("Ouvre aujourd'hui à %s", $slot['start']),
                ];
            }
        }

        for ($offset = 1; $offset <= 7; $offset++) {
            $dayOrder = (($todayOrder - 1 + $offset) % 7) + 1;
            $day = $byOrder[$dayOrder] ?? null;
            if ($day === null || !$day->isWorking()) {
                continue;
            }

            $slots = $this->extractSlots($day);
            if ($slots === []) {
                continue;
            }

            return [
                'isOpen' => false,
                'label' => 'Fermé',
                'detail' => sprintf('Ouvre %s à %s', $day->getJour()?->getLibelle() ?? 'ce jour', $slots[0]['start']),
            ];
        }

        return [
            'isOpen' => false,
            'label' => 'Fermé',
            'detail' => 'Aucun horaire renseigné',
        ];
    }

    /**
     * @param DayOfWork[] $dayOfWorks
     * @return array<int, array{shortDay: string, dayNumber: string, isAvailable: bool, isToday: bool, isSelected: bool, dayOffset: int}>
     */
    private function buildWeekAvailability(array $dayOfWorks, \DateTimeImmutable $now, int $selectedDayOffset): array
    {
        $result = [];
        $byOrder = $this->indexByDayOrder($dayOfWorks);
        $todayOrder = (int) $now->format('N');
        $shortDays = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];

        for ($offset = 0; $offset < 7; $offset++) {
            $date = $now->modify(sprintf('+%d day', $offset));
            $dayOrder = (int) $date->format('N');
            $day = $byOrder[$dayOrder] ?? null;
            $hasSlots = $day !== null && $day->isWorking() && $this->extractSlots($day) !== [];

            $result[] = [
                'shortDay' => $shortDays[$dayOrder],
                'dayNumber' => $date->format('j'),
                'isAvailable' => $hasSlots,
                'isToday' => $dayOrder === $todayOrder,
                'isSelected' => $offset === $selectedDayOffset,
                'dayOffset' => $offset,
            ];
        }

        return $result;
    }

    /**
     * @param DayOfWork[] $dayOfWorks
     * @return array<int, array{start: string, end: string}>
     */
    private function findDaySlots(array $dayOfWorks, int $dayOrder): array
    {
        $byOrder = $this->indexByDayOrder($dayOfWorks);
        $day = $byOrder[$dayOrder] ?? null;

        if ($day === null || !$day->isWorking()) {
            return [];
        }

        return $this->extractSlots($day);
    }

    /**
     * @param DayOfWork[] $dayOfWorks
     * @return array<int, array{start: string, end: string}>
     */
    private function findDayHalfHourSlots(array $dayOfWorks, int $dayOrder): array
    {
        $periods = $this->findDaySlots($dayOfWorks, $dayOrder);
        $halfHourSlots = [];

        foreach ($periods as $period) {
            $cursor = \DateTimeImmutable::createFromFormat('H:i', $period['start']);
            $end = \DateTimeImmutable::createFromFormat('H:i', $period['end']);

            if (!$cursor || !$end || $cursor >= $end) {
                continue;
            }

            while ($cursor < $end) {
                $next = $cursor->modify('+30 minutes');
                if ($next > $end) {
                    $next = $end;
                }

                $halfHourSlots[] = [
                    'start' => $this->formatHourLabel($cursor),
                    'end' => $this->formatHourLabel($next),
                ];

                $cursor = $next;
            }
        }

        return $halfHourSlots;
    }

    /**
     * @param DayOfWork[] $dayOfWorks
     * @return array<int, DayOfWork>
     */
    private function indexByDayOrder(array $dayOfWorks): array
    {
        $indexed = [];
        foreach ($dayOfWorks as $dayOfWork) {
            $order = $dayOfWork->getJour()?->getOrdre();
            if ($order === null) {
                continue;
            }

            $indexed[$order] = $dayOfWork;
        }

        return $indexed;
    }

    /**
     * @return array<int, array{start: string, end: string}>
     */
    private function extractSlots(DayOfWork $dayOfWork): array
    {
        $slots = [];

        foreach ($dayOfWork->getHoraires() as $horaire) {
            if ($horaire->getMorningStart() !== null && $horaire->getMorningEnd() !== null) {
                $slots[] = [
                    'start' => $horaire->getMorningStart()->format('H:i'),
                    'end' => $horaire->getMorningEnd()->format('H:i'),
                ];
            }

            if ($horaire->getAfternoonStart() !== null && $horaire->getAfternoonEnd() !== null) {
                $slots[] = [
                    'start' => $horaire->getAfternoonStart()->format('H:i'),
                    'end' => $horaire->getAfternoonEnd()->format('H:i'),
                ];
            }
        }

        usort($slots, static fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $slots;
    }

    private function formatHourLabel(\DateTimeImmutable $time): string
    {
        return $time->format('G:i');
    }
}
