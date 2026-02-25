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

        $lat = $request->query->get('lat');
        $lon = $request->query->get('lon');
        $distance = $request->query->getInt('distance', 20); // Default 20 km

        // Ensure positive values
        $page = max(1, $page);
        $limit = max(1, $limit);

        // Convert lat/lon to float if present
        $lat = $lat !== null && $lat !== '' ? (float) $lat : null;
        $lon = $lon !== null && $lon !== '' ? (float) $lon : null;

        $vets = $userRepository->findAllVets($page, $limit, $lat, $lon, $distance);
        $totalItems = count($vets);
        $totalPages = ceil($totalItems / $limit);

        return $this->render('search-vet/searchvet.html.twig', [
            'controller_name' => 'SearchVetController',
            'vets' => $vets,
            'currentPage' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'lat' => $lat,
            'lon' => $lon,
            'distance' => $distance,
            'location' => $request->query->get('location'),
        ]);
    }
}
