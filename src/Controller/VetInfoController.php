<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VetInfoController extends AbstractController
{
    #[Route('/vet/{id}', name: 'app_vet_info')]
    public function index(int $id): Response
    {
        // Mock data for now
        $vet = [
            'id' => $id,
            'name' => 'Dr. Martin DUPONT',
            'title' => 'Vétérinaire Généraliste',
            'address' => '123 Rue de la Santé, 75000 Ville',
            'distance' => '1,2 km',
            'image' => 'https://ui-avatars.com/api/?name=Martin+Dupont&background=random&size=200',
            'specialties' => ['Dermatologie', 'Gastroentérologie', 'Chirurgie'],
            'payment_methods' => 'CB / espèces / mutuelle animale',
            'animal_types' => 'Chiens / Chats / NAC / Chevaux',
            'consultation_duration' => '20 minutes',
            'languages' => 'FR / ENG',
            'phone' => '+12 345 6789 0',
            'email' => 'davidhere@mail.com', // From mock
        ];

        return $this->render('vet-info/vetinfo.html.twig', [
            'vet' => $vet,
        ]);
    }
}
