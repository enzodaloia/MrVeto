<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
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
}
