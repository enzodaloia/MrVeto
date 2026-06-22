<?php

namespace App\Controller;

use App\Entity\DayOfWork;
use App\Entity\VetProfile;
use App\Repository\SpecialiteRepository;
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
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VetInfoController extends AbstractController
{
    #[Route('/veterinaire/{slug}', name: 'app_vet_info')]
    public function index(#[MapEntity(mapping: ['slug' => 'slug'])] User $vet = null, DayOfWorkRepository $dayOfWorkRepository, SpecialiteRepository $specialiteRepository): Response
    {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles()) || $vet->getArchivedAt() !== null) {
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
            'vet'               => $vet,
            'vetProfile'        => $vet->getVetProfile(),
            'allSpecialites'    => $specialiteRepository->findAllOrdered(),
            'status'            => $status,
            'weekAvailability'  => $weekAvailability,
            'todaySlots'        => $todaySlots,
            'selectedDayLabel'  => $dayLabels[$selectedDayOrder] ?? strtolower($selectedDate->format('l')),
            'selectedDayDisplay' => $dayDisplaysByOffset[$selectedDayOffset] ?? (($dayLabels[$selectedDayOrder] ?? strtolower($selectedDate->format('l'))) . ' ' . $selectedDate->format('j')),
            'daySlotsByOffset'  => $daySlotsByOffset,
            'dayLabelsByOffset' => $dayLabelsByOffset,
            'dayDisplaysByOffset' => $dayDisplaysByOffset,
        ]);
    }

    /**
     * PATCH /veterinaire/{slug}/profil
     * Sauvegarde un champ du profil vétérinaire (inline edit).
     * Réservé au vétérinaire propriétaire de la fiche.
     */
    #[Route('/veterinaire/{slug}/profil', name: 'app_vet_profile_patch', methods: ['PATCH'])]
    public function patchProfile(
        #[MapEntity(mapping: ['slug' => 'slug'])] User $vet,
        Request $request,
        EntityManagerInterface $em,
        SpecialiteRepository $specialiteRepository,
    ): JsonResponse {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles()) || $vet->getArchivedAt() !== null) {
            return $this->json(['error' => 'Vétérinaire introuvable.'], 404);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser === null || $currentUser->getId() !== $vet->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $field = $data['field'] ?? null;
        $value = $data['value'] ?? null;

        $allowedFields = ['aPropos', 'specialites', 'moyensPaiement', 'animauxAcceptes', 'dureeConsultation', 'langues', 'isUrgentiste'];
        if (!in_array($field, $allowedFields, true)) {
            return $this->json(['error' => 'Champ non autorisé.'], 400);
        }

        $profile = $vet->getVetProfile();
        if ($profile === null) {
            $profile = new VetProfile();
            $profile->setUser($vet);
            $vet->setVetProfile($profile);
            $em->persist($profile);
        }

        // Cas particulier : spécialités → ManyToMany Specialite
        if ($field === 'specialites') {
            $items = $this->syncSpecialites($profile, is_array($value) ? $value : [], $specialiteRepository, $em);
            $em->flush();
            return $this->json([
                'success' => true,
                'field'   => 'specialites',
                'items'   => $items,
            ]);
        }

        $setter = 'set' . ucfirst($field);
        if ($field === 'isUrgentiste') {
            $value = (bool) $value;
        }
        $profile->$setter($value);
        $em->flush();

        return $this->json(['success' => true, 'field' => $field, 'value' => $value]);
    }

    /**
     * Synchronise la liste de spécialités du VetProfile à partir des slugs reçus.
     * Seuls les slugs correspondant à une Specialite existante en BDD sont accept\u00e9s
     * (pas de cr\u00e9ation de spécialités custom).
     *
     * @param string[] $values
     * @return array<int, array{slug: string, label: string}>
     */
    private function syncSpecialites(
        VetProfile $profile,
        array $values,
        SpecialiteRepository $repo,
        EntityManagerInterface $em,
    ): array {
        // Vider la collection actuelle
        foreach ($profile->getSpecialites()->toArray() as $existing) {
            $profile->removeSpecialite($existing);
        }

        $result = [];
        foreach ($values as $raw) {
            $slug = trim((string) $raw);
            if ($slug === '') continue;

            $sp = $repo->findOneBySlug($slug);
            if ($sp === null) {
                continue; // slug inconnu : ignor\u00e9
            }

            $profile->addSpecialite($sp);
            $result[] = ['slug' => $sp->getSlug(), 'label' => $sp->getLabel()];
        }

        return $result;
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

    #[Route('/veterinaire/{slug}/reserver/j/{date}', name: 'app_vet_book_day', requirements: ['date' => '\\d{4}-\\d{2}-\\d{2}'], methods: ['GET'])]
    public function bookDay(
        #[MapEntity(mapping: ['slug' => 'slug'])] User $vet,
        string $date,
        DayOfWorkRepository $dayOfWorkRepository,
        AnimalRepository $animalRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles()) || $vet->getArchivedAt() !== null) {
            throw new NotFoundHttpException('Vétérinaire non trouvé.');
        }

        return $this->renderBookingPage($vet, substr($date, 0, 7), $date, $dayOfWorkRepository, $animalRepository, $em);
    }

    #[Route('/veterinaire/{slug}/reserver/m/{month}', name: 'app_vet_book_month', requirements: ['month' => '\\d{4}-\\d{2}'], methods: ['GET'])]
    public function bookMonth(
        #[MapEntity(mapping: ['slug' => 'slug'])] User $vet,
        string $month,
        DayOfWorkRepository $dayOfWorkRepository,
        AnimalRepository $animalRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles()) || $vet->getArchivedAt() !== null) {
            throw new NotFoundHttpException('Vétérinaire non trouvé.');
        }

        return $this->renderBookingPage($vet, $month, null, $dayOfWorkRepository, $animalRepository, $em);
    }

    #[Route('/veterinaire/{slug}/reserver', name: 'app_vet_book', methods: ['GET'])]
    public function book(
        #[MapEntity(mapping: ['slug' => 'slug'])] User $vet,
        DayOfWorkRepository $dayOfWorkRepository,
        AnimalRepository $animalRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles()) || $vet->getArchivedAt() !== null) {
            throw new NotFoundHttpException('Vétérinaire non trouvé.');
        }

        return $this->renderBookingPage($vet, null, null, $dayOfWorkRepository, $animalRepository, $em);
    }

    private function renderBookingPage(
        User $vet,
        ?string $monthParam,
        ?string $selectedDateParam,
        DayOfWorkRepository $dayOfWorkRepository,
        AnimalRepository $animalRepository,
        EntityManagerInterface $em,
    ): Response {
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
                    'age' => $animal->getAge() ?? '',
                    'vaccinAJour' => $animal->isVaccinAJour(),
                    'poids' => $animal->getPoids() ?? '',
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

    #[Route('/veterinaire/{slug}/reserver/confirmation', name: 'app_vet_book_confirm', methods: ['POST'])]
    public function confirmBooking(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] User $vet = null, AnimalRepository $animalRepository, EntityManagerInterface $em): Response
    {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles()) || $vet->getArchivedAt() !== null) {
            throw new NotFoundHttpException('Vétérinaire non trouvé.');
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // 1. Process animal save/update
        $animalId = $request->request->get('animalId');
        $animal = null;

        // Verify if we have minimal data to save an animal (requires at least 'animalNom')
        $animalNom = trim($request->request->get('animalNom', ''));
        if (!empty($animalNom)) {
            // Update existing animal (only if it belongs to the current user)
            if ($animalId) {
                $animal = $animalRepository->find($animalId);
                if (!$animal || $animal->getProprietaire() !== $user) {
                    $animal = null;
                }
            }

            // Create new animal if not updating
            if (!$animal) {
                $animal = new \App\Entity\Animal();
                $animal->setProprietaire($user);
                $em->persist($animal);
            }

            $animal->setNom($animalNom);
            $animal->setEspece($request->request->get('animalEspece'));
            $animal->setRace($request->request->get('animalRace'));
            $animal->setPoids($request->request->get('animalPoids'));
            $animal->setVaccinAJour($request->request->get('vaccin') === 'oui');

            $age = $request->request->get('animalAge');
            $animal->setAge(empty($age) ? null : $age);

            $em->flush();
            $animalId = $animal->getId(); // Override animalId with the newly created/updated one
        }

        // 2. Process booking data
        $selectedDate = $request->request->get('selectedDate');
        $selectedSlot = $request->request->get('selectedSlot');
        
        $rdvMotif = $request->request->get('rdvMotif');
        $rdvRemarque = $request->request->get('rdvRemarque');

        // Check if date and slot are provided
        if (empty($selectedDate) || empty($selectedSlot)) {
            $this->addFlash('error', 'Veuillez sélectionner une date et un créneau horaire.');
            return $this->redirectToRoute('app_vet_book', ['slug' => $vet->getSlug()]);
        }

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
        if ($animal) {
            $animalName = $animal->getNom() . ($animal->getEspece() ? ' (' . $animal->getEspece() . ')' : '');
        } elseif ($animalId) {
            $existingAnimal = $animalRepository->find($animalId);
            if ($existingAnimal && $existingAnimal->getProprietaire() === $user) {
                $animalName = $existingAnimal->getNom() . ($existingAnimal->getEspece() ? ' (' . $existingAnimal->getEspece() . ')' : '');
            }
        }

        return $this->render('vet-info/book_confirmation.html.twig', [
            'vet' => $vet,
            'dateDisplay' => $dateDisplay,
            'selectedDate' => $selectedDate,
            'selectedSlot' => $selectedSlot,
            'animalId' => $animalId,
            'animalName' => $animalName,
            'rdvMotif' => $rdvMotif,
            'rdvRemarque' => $rdvRemarque,
        ]);
    }

    #[Route('/veterinaire/{slug}/reserver/sauvegarder', name: 'app_vet_book_save', methods: ['POST'])]
    public function saveRendezVous(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] User $vet = null, EntityManagerInterface $em, AnimalRepository $animalRepository): Response
    {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles()) || $vet->getArchivedAt() !== null) {
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
            return $this->redirectToRoute('app_vet_book', ['slug' => $vet->getSlug()]);
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
        
        $rdvMotif = $request->request->get('rdvMotif');
        if ($rdvMotif) {
            $rdv->setMotif($rdvMotif);
        }
        
        $rdvRemarque = $request->request->get('rdvRemarque');
        if ($rdvRemarque) {
            $rdv->setRemarque($rdvRemarque);
        }

        $em->persist($rdv);
        $em->flush();

        if ($request->isXmlHttpRequest() || in_array('application/json', $request->getAcceptableContentTypes())) {
            return new JsonResponse(['success' => true]);
        }

        $this->addFlash('success', 'Votre rendez-vous a été confirmé avec succès !');

        return $this->redirectToRoute('app_rendezvous');
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
