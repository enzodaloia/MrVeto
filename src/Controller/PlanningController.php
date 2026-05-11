<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\DayOfWorkRepository;
use App\Repository\RendezVousRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vet/planning', name: 'app_vet_planning')]
class PlanningController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(DayOfWorkRepository $dayOfWorkRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        /** @var User $user */
        $user = $this->getUser();

        // Build businessHours from DayOfWork + Horaire
        $daysOfWork = $dayOfWorkRepository->createQueryBuilder('d')
            ->innerJoin('d.jour', 'j')->addSelect('j')
            ->leftJoin('d.horaires', 'h')->addSelect('h')
            ->andWhere('d.user = :u')->setParameter('u', $user)
            ->andWhere('d.isWorking = true')
            ->orderBy('j.ordre', 'ASC')
            ->getQuery()->getResult();

        // FullCalendar dow: 0=Sunday … 6=Saturday
        // Our ordre: 1=Lundi … 7=Dimanche
        $ordreToFcDow = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 0];

        $businessHours = [];
        foreach ($daysOfWork as $dow) {
            $horaire = $dow->getHoraires()->first();
            if (!$horaire) {
                continue;
            }
            $fcDow = $ordreToFcDow[$dow->getJour()->getOrdre()];

            if ($horaire->getMorningStart() && $horaire->getMorningEnd()) {
                $businessHours[] = [
                    'daysOfWeek' => [$fcDow],
                    'startTime' => $horaire->getMorningStart()->format('H:i'),
                    'endTime' => $horaire->getMorningEnd()->format('H:i'),
                ];
            }
            if ($horaire->getAfternoonStart() && $horaire->getAfternoonEnd()) {
                $businessHours[] = [
                    'daysOfWeek' => [$fcDow],
                    'startTime' => $horaire->getAfternoonStart()->format('H:i'),
                    'endTime' => $horaire->getAfternoonEnd()->format('H:i'),
                ];
            }
        }

        return $this->render('planning/index.html.twig', [
            'businessHours' => $businessHours,
        ]);
    }

    #[Route('/events', name: '_events', methods: ['GET'])]
    public function events(Request $request, RendezVousRepository $rendezVousRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        /** @var User $user */
        $user = $this->getUser();

        $startStr = $request->query->get('start');
        $endStr = $request->query->get('end');

        $tz = new \DateTimeZone('Europe/Paris');

        try {
            $start = $startStr ? new \DateTime($startStr, $tz) : new \DateTime('monday this week', $tz);
            $end = $endStr ? new \DateTime($endStr, $tz) : new \DateTime('sunday this week 23:59:59', $tz);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date parameters'], 400);
        }

        $rdvList = $rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.animal', 'a')->addSelect('a')
            ->andWhere('r.veterinaire = :vet')
            ->andWhere('r.dateHeure >= :start')
            ->andWhere('r.dateHeure < :end')
            ->setParameter('vet', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('r.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();

        $statusColors = [
            'en_attente' => ['bg' => '#FFCF00', 'border' => '#e6bb00', 'text' => '#212529'],
            'termine'    => ['bg' => '#36BDAF', 'border' => '#2da89b', 'text' => '#ffffff'],
            'annule'     => ['bg' => '#9e9e9e', 'border' => '#757575', 'text' => '#ffffff'],
            'deplace'    => ['bg' => '#fd7e14', 'border' => '#e0710f', 'text' => '#ffffff'],
        ];

        $events = [];
        foreach ($rdvList as $rdv) {
            $statut = $rdv->getStatut() ?? 'en_attente';
            $colors = $statusColors[$statut] ?? $statusColors['en_attente'];

            $start = clone $rdv->getDateHeure();
            $end = (clone $rdv->getDateHeure())->modify('+30 minutes');

            $clientName = $rdv->getClient()
                ? $rdv->getClient()->getPrenom() . ' ' . $rdv->getClient()->getNom()
                : 'Client inconnu';
            $animalName = $rdv->getAnimal()?->getNom() ?? '';

            $title = $animalName ? $animalName . ' – ' . ($rdv->getMotif() ?? '') : ($rdv->getMotif() ?? 'RDV');

            $events[] = [
                'id' => $rdv->getId(),
                'title' => $title,
                'start' => $start->format('Y-m-d\TH:i:s'),
                'end' => $end->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $colors['bg'],
                'borderColor' => $colors['border'],
                'textColor' => $colors['text'],
                'extendedProps' => [
                    'statut' => $statut,
                    'client' => $clientName,
                    'animal' => $animalName,
                    'motif' => $rdv->getMotif() ?? '',
                    'slug' => method_exists($rdv, 'getSlug') ? $rdv->getSlug() : null,
                ],
            ];
        }

        return $this->json($events);
    }
}
