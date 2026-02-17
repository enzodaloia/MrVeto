<?php

namespace App\Controller;

use App\Repository\UserRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchVetController extends AbstractController
{
    #[Route('/front/search', name: 'app_search_vet')]
    public function index(UserRepository $userRepository): Response
    {
        $vets = $userRepository->findAllVets();

        return $this->render('search-vet/searchvet.html.twig', [
            'controller_name' => 'SearchVetController',
            'vets' => $vets,
        ]);
    }
}
