<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Entity\Animal;
use App\Repository\AnimalRepository;
use App\Repository\DayOfWorkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VetInfoController extends AbstractController
{
    #[Route('/vet/{id}', name: 'app_vet_info')]
    public function index(User $vet = null): Response
    {
        if (!$vet || !in_array('ROLE_VETO', $vet->getRoles())) {
            throw new NotFoundHttpException('Vétérinaire non trouvé.');
        }

        return $this->render('vet-info/vetinfo.html.twig', [
            'vet' => $vet,
        ]);
    }

    #[Route('/vet/{id}/book', name: 'app_vet_book')]
    public function book(Request $request, User $vet = null, DayOfWorkRepository $dayOfWorkRepository): Response
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

                // Generate 30-min slots for morning
                if ($dayData['morningStart'] && $dayData['morningEnd']) {
                    $morningSlots = $this->generateTimeSlots($dayData['morningStart'], $dayData['morningEnd'], 30);
                }

                // Generate 30-min slots for afternoon
                if ($dayData['afternoonStart'] && $dayData['afternoonEnd']) {
                    $afternoonSlots = $this->generateTimeSlots($dayData['afternoonStart'], $dayData['afternoonEnd'], 30);
                }
            }
        }

        // --- User's animals ---
        $user = $this->getUser();
        $animals = [];
        $animalsJson = '[]';
        if ($user) {
            $animals = $user->getAnimals()->toArray();
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

    /**
     * Generate time slots between start and end with given interval in minutes.
     */
    private function generateTimeSlots(\DateTime $start, \DateTime $end, int $intervalMinutes): array
    {
        $slots = [];
        $current = clone $start;

        while ($current < $end) {
            $slots[] = $current->format('H:i');
            $current->modify("+{$intervalMinutes} minutes");
        }

        return $slots;
    }
}
