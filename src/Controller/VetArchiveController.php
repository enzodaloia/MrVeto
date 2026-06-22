<?php

namespace App\Controller;

use App\Repository\RendezVousRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/veterinaire/archives')]
class VetArchiveController extends AbstractController
{
    #[Route('', name: 'app_archives', methods: ['GET'])]
    public function index(RendezVousRepository $rendezVousRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        /** @var \App\Entity\User $vet */
        $vet = $this->getUser();

        $archives = $rendezVousRepository->findArchivesForVeterinaire($vet);

        return $this->render('vet_archives/index.html.twig', [
            'archives' => $archives,
        ]);
    }
}
