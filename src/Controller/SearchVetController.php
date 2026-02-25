<?php

namespace App\Controller;

use App\Repository\UserRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SearchVetController extends AbstractController
{
    #[Route('/front/search', name: 'app_search_vet')]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        // Ensure positive values
        $page = max(1, $page);
        $limit = max(1, $limit);

        $vets = $userRepository->findAllVets($page, $limit);
        $totalItems = count($vets);
        $totalPages = ceil($totalItems / $limit);

        return $this->render('search-vet/searchvet.html.twig', [
            'controller_name' => 'SearchVetController',
            'vets' => $vets,
            'currentPage' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
        ]);
    }
}
