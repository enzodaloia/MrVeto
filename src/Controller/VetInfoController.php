<?php

namespace App\Controller;

use App\Entity\DayOfWork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Entity\Animal;
use App\Entity\RendezVous;
use App\Repository\AnimalRepository;
use App\Repository\DayOfWorkRepository;
use Doctrine\ORM\EntityManagerInterface;
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

    #[Route('/vet/{id}/book', name: 'app_vet_book')]
    public function book(Request $request, User $vet = null, DayOfWorkRepository $dayOfWorkRepository, AnimalRepository $animalRepository, EntityManagerInterface $em): Response
    {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles())) {
            throw new NotFoundHttpException('Vétérinaire non trouvé.');
        }

        // --- Fetch vet's working days with horaires ---
        $daysOfWork = $dayOfWorkRepository->createQueryBuilder('d')
            ->innerJoin('d.jour', 'j')->addSelect('j')
            ->leftJoin('d.horaires', 'h')->addSelect('h')
            ->andWhere('d.user = :u')->setParameter('u', $vet)
            ->orderBy('j.ordre', 'ASC')
            ->getQuery()->getResult();

        // Build workingDays indexed by Jour.ordre (1=Monday ... 7=Sunday)
        // Contains horaire data for each working day
        $workingDays = [];
        foreach ($daysOfWork as $dow) {
            if (!$dow->isWorking()) {
                continue;
            }
            $ordre = $dow->getJour()->getOrdre();
            $horaire = $dow->getHoraires()->first();
            $workingDays[$ordre] = [
                'jourLibelle' => $dow->getJour()->getLibelle(),
                'morningStart' => $horaire ? $horaire->getMorningStart() : null,
                'morningEnd' => $horaire ? $horaire->getMorningEnd() : null,
                'afternoonStart' => $horaire ? $horaire->getAfternoonStart() : null,
                'afternoonEnd' => $horaire ? $horaire->getAfternoonEnd() : null,
            ];
        }

        // --- Calendar computation ---
        $monthParam = $request->query->get('month');
        $selectedDateParam = $request->query->get('date');

        $today = new \DateTime('today');

        if ($monthParam && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $year = (int) substr($monthParam, 0, 4);
            $month = (int) substr($monthParam, 5, 2);
        } else {
            $year = (int) $today->format('Y');
            $month = (int) $today->format('m');
        }

        $firstDay = new \DateTime("$year-$month-01");
        $daysInMonth = (int) $firstDay->format('t');

        // Day of week for the 1st: 1=Monday ... 7=Sunday (ISO)
        $firstDayOfWeek = (int) $firstDay->format('N');

        // Build calendar grid (array of weeks, each week = array of 7 day entries)
        $weeks = [];
        $currentWeek = array_fill(0, 7, null);
        $dayNumber = 1;

        for ($i = $firstDayOfWeek - 1; $i < 7 && $dayNumber <= $daysInMonth; $i++) {
            $date = new \DateTime("$year-$month-$dayNumber");
            $dayOfWeekIso = (int) $date->format('N'); // 1=Mon..7=Sun
            $isWorking = isset($workingDays[$dayOfWeekIso]);
            $isPast = $date < $today;
            $currentWeek[$i] = [
                'number' => $dayNumber,
                'date' => $date->format('Y-m-d'),
                'isWorking' => $isWorking,
                'isPast' => $isPast,
                'isToday' => $date->format('Y-m-d') === $today->format('Y-m-d'),
            ];
            $dayNumber++;
        }
        $weeks[] = $currentWeek;

        while ($dayNumber <= $daysInMonth) {
            $currentWeek = array_fill(0, 7, null);
            for ($i = 0; $i < 7 && $dayNumber <= $daysInMonth; $i++) {
                $date = new \DateTime("$year-$month-$dayNumber");
                $dayOfWeekIso = (int) $date->format('N');
                $isWorking = isset($workingDays[$dayOfWeekIso]);
                $isPast = $date < $today;
                $currentWeek[$i] = [
                    'number' => $dayNumber,
                    'date' => $date->format('Y-m-d'),
                    'isWorking' => $isWorking,
                    'isPast' => $isPast,
                    'isToday' => $date->format('Y-m-d') === $today->format('Y-m-d'),
                ];
                $dayNumber++;
            }
            $weeks[] = $currentWeek;
        }

        // --- Prev/Next month ---
        $prevMonth = (clone $firstDay)->modify('-1 month');
        $nextMonth = (clone $firstDay)->modify('+1 month');

        // --- Selected date & time slots ---
        $selectedDate = null;
        $morningSlots = [];
        $afternoonSlots = [];

        if ($selectedDateParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDateParam)) {
            $selectedDate = new \DateTime($selectedDateParam);
            $selectedDayOfWeek = (int) $selectedDate->format('N');

            if (isset($workingDays[$selectedDayOfWeek]) && $selectedDate >= $today) {
                $dayData = $workingDays[$selectedDayOfWeek];

                // Fetch existing appointments for this day to disable taken slots
                $startOfDay = clone $selectedDate;
                $startOfDay->setTime(0, 0, 0);
                $endOfDay = clone $selectedDate;
                $endOfDay->setTime(23, 59, 59);

                $appointments = $em->getRepository(RendezVous::class)->createQueryBuilder('r')
                    ->where('r.veterinaire = :vet')
                    ->andWhere('r.dateHeure >= :start')
                    ->andWhere('r.dateHeure <= :end')
                    ->andWhere('r.statut != :cancelled')
                    ->setParameter('vet', $vet)
                    ->setParameter('start', $startOfDay)
                    ->setParameter('end', $endOfDay)
                    ->setParameter('cancelled', 'annule')
                    ->getQuery()
                    ->getResult();

                $bookedSlots = [];
                foreach ($appointments as $appt) {
                    $bookedSlots[] = $appt->getDateHeure()->format('H:i');
                }

                // Generate 30-min slots for morning
                if ($dayData['morningStart'] && $dayData['morningEnd']) {
                    $morningSlots = $this->generateTimeSlots($dayData['morningStart'], $dayData['morningEnd'], 30, $bookedSlots);
                }

                // Generate 30-min slots for afternoon
                if ($dayData['afternoonStart'] && $dayData['afternoonEnd']) {
                    $afternoonSlots = $this->generateTimeSlots($dayData['afternoonStart'], $dayData['afternoonEnd'], 30, $bookedSlots);
                }
            }
        }

        // --- User's animals ---
        $user = $this->getUser();
        $animals = [];
        $animalsJson = '[]';
        if ($user instanceof User) {
            $animals = $animalRepository->findBy(['proprietaire' => $user]);
            $animalsData = [];
            foreach ($animals as $animal) {
                $animalsData[] = [
                    'id' => $animal->getId(),
                    'nom' => $animal->getNom(),
                    'espece' => $animal->getEspece(),
                    'race' => $animal->getRace(),
                    'dateNaissance' => $animal->getDateNaissance() ? $animal->getDateNaissance()->format('Y-m-d') : '',
                    'vaccinAJour' => $animal->isVaccinAJour(),
                    'poids' => $animal->getPoids() ?? '',
                    'remarque' => $animal->getRemarque() ?? '',
                ];
            }
            $animalsJson = json_encode($animalsData);
        }

        // Month label in French
        $monthNames = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        return $this->render('vet-info/book.html.twig', [
            'vet' => $vet,
            'weeks' => $weeks,
            'currentMonth' => $month,
            'currentYear' => $year,
            'monthLabel' => $monthNames[$month] . ' ' . $year,
            'prevMonth' => $prevMonth->format('Y-m'),
            'nextMonth' => $nextMonth->format('Y-m'),
            'selectedDate' => $selectedDate ? $selectedDate->format('Y-m-d') : null,
            'morningSlots' => $morningSlots,
            'afternoonSlots' => $afternoonSlots,
            'hasWorkingDays' => !empty($workingDays),
            'animals' => $animals,
            'animalsJson' => $animalsJson,
        ]);
    }

    #[Route('/vet/{id}/book/save-animal', name: 'app_vet_book_save_animal', methods: ['POST'])]
    public function saveAnimal(Request $request, User $vet = null, EntityManagerInterface $em, AnimalRepository $animalRepository): Response
    {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles())) {
            throw new NotFoundHttpException('Vétérinaire non trouvé.');
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $animalId = $request->request->get('animalId');
        $animal = null;

        // Update existing animal (only if it belongs to the current user)
        if ($animalId) {
            $animal = $animalRepository->find($animalId);
            if (!$animal || $animal->getProprietaire() !== $user) {
                $animal = null;
            }
        }

        // Create new animal if not updating
        if (!$animal) {
            $animal = new Animal();
            $animal->setProprietaire($user);
            $em->persist($animal);
        }

        $animal->setNom($request->request->get('animalNom', ''));
        $animal->setEspece($request->request->get('animalEspece'));
        $animal->setRace($request->request->get('animalRace'));
        $animal->setPoids($request->request->get('animalPoids'));
        $animal->setRemarque($request->request->get('animalRemarque'));
        $animal->setVaccinAJour($request->request->get('vaccin') === 'oui');

        $dateNaissance = $request->request->get('animalDateNaissance');
        if ($dateNaissance) {
            try {
                $animal->setDateNaissance(new \DateTime($dateNaissance));
            } catch (\Exception $e) {
                // Ignore invalid date
            }
        } else {
            $animal->setDateNaissance(null);    
        }

        $em->flush();

        $this->addFlash('success', $animalId ? 'Animal mis à jour !' : 'Animal créé avec succès !');

        // Redirect back preserving current query params
        return $this->redirectToRoute('app_vet_book', [
            'id' => $vet->getId(),
            'month' => $request->request->get('_month'),
            'date' => $request->request->get('_date'),
            'slot' => $request->request->get('_slot'),
        ]);
    }

    #[Route('/vet/{id}/book/confirm', name: 'app_vet_book_confirm', methods: ['POST'])]
    public function confirmBooking(Request $request, User $vet = null, AnimalRepository $animalRepository): Response
    {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles())) {
            throw new NotFoundHttpException('Vétérinaire non trouvé.');
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $selectedDate = $request->request->get('selectedDate');
        $selectedSlot = $request->request->get('selectedSlot');
        $animalId = $request->request->get('animalId');

        // Format date for display
        $dateDisplay = '';
        if ($selectedDate && $selectedSlot) {
            try {
                $dt = new \DateTime($selectedDate . ' ' . $selectedSlot);
                $monthsFr = [1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',7=>'juillet',8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre'];
                $dateDisplay = $dt->format('d') . ' ' . $monthsFr[(int)$dt->format('m')] . ' ' . $dt->format('Y') . ' à ' . $dt->format('H:i');
            } catch (\Exception $e) {
                $dateDisplay = $selectedDate . ' ' . $selectedSlot;
            }
        }

        // Get animal name
        $animalName = 'Non sélectionné';
        if ($animalId) {
            $animal = $animalRepository->find($animalId);
            if ($animal && $animal->getProprietaire() === $user) {
                $animalName = $animal->getNom() . ($animal->getEspece() ? ' (' . $animal->getEspece() . ')' : '');
            }
        }

        return $this->render('vet-info/book_confirmation.html.twig', [
            'vet' => $vet,
            'dateDisplay' => $dateDisplay,
            'selectedDate' => $selectedDate,
            'selectedSlot' => $selectedSlot,
            'animalId' => $animalId,
            'animalName' => $animalName,
        ]);
    }

    #[Route('/vet/{id}/book/save', name: 'app_vet_book_save', methods: ['POST'])]
    public function saveRendezVous(Request $request, User $vet = null, EntityManagerInterface $em, AnimalRepository $animalRepository): Response
    {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles())) {
            throw new NotFoundHttpException('Vétérinaire non trouvé.');
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $selectedDate = $request->request->get('selectedDate');
        $selectedSlot = $request->request->get('selectedSlot');
        $animalId = $request->request->get('animalId');

        // Build dateHeure
        $dateHeure = new \DateTime($selectedDate . ' ' . $selectedSlot);

        // Check backend if slot is already taken
        $existingRdv = $em->getRepository(RendezVous::class)->findOneBy([
            'veterinaire' => $vet,
            'dateHeure' => $dateHeure
        ]);

        // Optionnel : ne pas compter les annulés
        if ($existingRdv && $existingRdv->getStatut() !== 'annule') {
            $this->addFlash('error', 'Désolé, ce créneau a déjà été réservé entre temps.');
            return $this->redirectToRoute('app_vet_book', ['id' => $vet->getId()]);
        }

        // Find animal
        $animal = null;
        if ($animalId) {
            $animal = $animalRepository->find($animalId);
            if ($animal && $animal->getProprietaire() !== $user) {
                $animal = null;
            }
        }

        $rdv = new RendezVous();
        $rdv->setClient($user);
        $rdv->setVeterinaire($vet);
        $rdv->setAnimal($animal);
        $rdv->setDateHeure($dateHeure);
        $rdv->setStatut('en_attente');

        $em->persist($rdv);
        $em->flush();

        if ($request->isXmlHttpRequest() || in_array('application/json', $request->getAcceptableContentTypes())) {
            return new JsonResponse(['success' => true]);
        }

        $this->addFlash('success', 'Votre rendez-vous a été confirmé avec succès !');

        return $this->redirectToRoute('app_vet_info', ['id' => $vet->getId()]);
    }

    /**
     * Generate time slots between start and end with given interval in minutes.
     */
    private function generateTimeSlots(\DateTime $start, \DateTime $end, int $intervalMinutes, array $bookedSlots = []): array
    {
        $slots = [];
        $current = clone $start;

        while ($current < $end) {
            $timeString = $current->format('H:i');
            $slots[] = [
                'time' => $timeString,
                'available' => !in_array($timeString, $bookedSlots)
            ];
            $current->modify("+{$intervalMinutes} minutes");
        }

        return $slots;
    }
}
