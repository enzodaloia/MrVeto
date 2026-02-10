<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchVetController extends AbstractController
{
    #[Route('/search', name: 'app_search_vet')]
    public function index(): Response
    {
        return $this->render('search-vet/searchvet.html.twig', [
            'controller_name' => 'SearchVetController',
        ]);
    }
}
