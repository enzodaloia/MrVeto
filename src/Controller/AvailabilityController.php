<?php

namespace App\Controller;

use App\Entity\DayOfWork;
use App\Entity\Horaire;
use App\Form\DisponibiliteType;
use App\Repository\DayOfWorkRepository;
use App\Repository\JourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

final class AvailabilityController extends AbstractController
{
    #[Route('/api/availability', name: 'api_availability', methods: ['GET'])]
    public function apiAvailability(
        DayOfWorkRepository $dayOfWorkRepository
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json([], 401);
        }

        $daysOfWork = $dayOfWorkRepository->createQueryBuilder('d')
            ->innerJoin('d.jour', 'j')->addSelect('j')
            ->leftJoin('d.horaires', 'h')->addSelect('h')
            ->andWhere('d.user = :u')->setParameter('u', $user)
            ->orderBy('j.ordre', 'ASC')
            ->getQuery()->getResult();

        // On génère les events pour la semaine courante
        $events = [];
        $monday = new \DateTime('monday this week');

        foreach ($daysOfWork as $dow) {
            // ordre de 1 (lundi) à 7 (dimanche)
            $ordre = $dow->getJour()->getOrdre();
            $date = (clone $monday)->modify('+' . ($ordre - 1) . ' days');
            $dateStr = $date->format('Y-m-d');

            $horaire = $dow->getHoraires()->first();

            if (!$horaire) {
                // Absent toute la journée
                $events[] = [
                    'title' => 'Absent',
                    'start' => $dateStr . 'T08:00:00',
                    'end' => $dateStr . 'T18:00:00',
                    'backgroundColor' => '#e0e0e0',
                    'borderColor' => '#bdbdbd',
                    'textColor' => '#757575',
                ];
                continue;
            }

            // Matinée
            if ($horaire->getMorningStart() && $horaire->getMorningEnd()) {
                $events[] = [
                    'title' => 'Disponible',
                    'start' => $dateStr . 'T' . $horaire->getMorningStart()->format('H:i:s'),
                    'end' => $dateStr . 'T' . $horaire->getMorningEnd()->format('H:i:s'),
                    'backgroundColor' => '#e8f8f6',
                    'borderColor' => '#1aab96',
                    'textColor' => '#1aab96',
                ];
            }

            // Après-midi
            if ($horaire->getAfternoonStart() && $horaire->getAfternoonEnd()) {
                $events[] = [
                    'title' => 'Disponible',
                    'start' => $dateStr . 'T' . $horaire->getAfternoonStart()->format('H:i:s'),
                    'end' => $dateStr . 'T' . $horaire->getAfternoonEnd()->format('H:i:s'),
                    'backgroundColor' => '#e8f8f6',
                    'borderColor' => '#1aab96',
                    'textColor' => '#1aab96',
                ];
            }
        }

        return $this->json($events);
    }
    #[Route('/availability', name: 'app_availability', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        DayOfWorkRepository $dayOfWorkRepository,
        JourRepository $jourRepository,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Créer les DayOfWork si premier accès
        if ($dayOfWorkRepository->countForUser($user) === 0) {
            foreach ($jourRepository->findBy([], ['ordre' => 'ASC']) as $jour) {
                $dow = new DayOfWork();
                $dow->setUser($user)->setJour($jour)->setIsWorking(false);
                $em->persist($dow);
            }
            $em->flush();
        }

        $daysOfWork = $dayOfWorkRepository->createQueryBuilder('d')
            ->innerJoin('d.jour', 'j')->addSelect('j')
            ->leftJoin('d.horaires', 'h')->addSelect('h')
            ->andWhere('d.user = :u')->setParameter('u', $user)
            ->orderBy('j.ordre', 'ASC')
            ->getQuery()->getResult();

        $horairesList = [];
        foreach ($daysOfWork as $dow) {
            if ($dow->getHoraires()->isEmpty()) {
                $h = new Horaire();
                $dow->addHoraire($h);
                $em->persist($h);
            } else {
                $h = $dow->getHoraires()->first();
            }

            // Dans les deux cas, on applique les défauts si les valeurs sont null
            if ($h->getMorningStart() === null)
                $h->setMorningStart(new \DateTime('08:00'));
            if ($h->getMorningEnd() === null)
                $h->setMorningEnd(new \DateTime('12:00'));
            if ($h->getAfternoonStart() === null)
                $h->setAfternoonStart(new \DateTime('13:00'));
            if ($h->getAfternoonEnd() === null)
                $h->setAfternoonEnd(new \DateTime('18:00'));

            $horairesList[] = $h;

            $form = $this->createForm(DisponibiliteType::class, ['horaires' => $horairesList], [
                'user' => $user,
            ]);

            $form->handleRequest($request);
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $workingDayIds = $request->request->all('working_days') ?? [];
            foreach ($daysOfWork as $dow) {
                $dow->setIsWorking(in_array($dow->getId(), array_map('intval', $workingDayIds)));
            }
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true]);
            }

            $this->addFlash('success', 'Disponibilités enregistrées.');
            return $this->redirectToRoute('app_availability');
        }

        return $this->render('availability/index.html.twig', [
            'daysOfWork' => $daysOfWork,
            'form' => $form->createView(),
        ]);
    }
}