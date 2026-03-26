<?php

namespace App\Controller;

use App\Repository\RendezVousRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RendezVousController extends AbstractController
{
    #[Route('/mes-rendez-vous', name: 'app_rendezvous')]
    public function index(RendezVousRepository $rendezVousRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $upcomingRendezVous = $rendezVousRepository->createQueryBuilder('r')
            ->andWhere('r.client = :user')
            ->andWhere('r.dateHeure >= :now')
            ->andWhere('r.statut != :annule')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();

        $pastRendezVous = $rendezVousRepository->createQueryBuilder('r')
            ->andWhere('r.client = :user')
            ->andWhere('r.dateHeure < :now OR r.statut = :annule')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('rendez_vous/index.html.twig', [
            'upcoming' => $upcomingRendezVous,
            'past' => $pastRendezVous,
        ]);
    }
}
