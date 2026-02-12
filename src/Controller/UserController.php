<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_user_profile')]
    public function profile(): Response
    {
        return $this->render('user/profile.html.twig');
    }
}
