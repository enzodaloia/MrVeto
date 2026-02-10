<?php
// src/Controller/TestMailerController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class TestMailerController extends AbstractController
{
    #[Route('/test-mail', name: 'test_mail')]
    public function testMail(MailerInterface $mailer): Response
    {
        try {
            $email = (new Email())
                ->from('contact@mrveto.fr')
                ->to('mathys.nourry@gmail.com')
                ->subject('Test Symfony Mailer')
                ->text('zobalobzobzob');

            $mailer->send($email);
            return new Response('Mail envoyé enfin !');
        } catch (\Throwable $e) {
            return new Response('Erreur lors de l’envoi : '.$e->getMessage());
        }
    }
}
