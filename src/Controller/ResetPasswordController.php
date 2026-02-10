<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, MailerInterface $mailer, TranslatorInterface $translator): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            // dd("Email reçu dans le formulaire : $email"); // <- DEBUG : on voit si le mail est bien récupéré

            return $this->processSendingPasswordResetEmail($email, $mailer, $translator);
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        $resetToken = $this->getTokenObjectFromSession() ?? $this->resetPasswordHelper->generateFakeResetToken();
        dd("Token stocké en session : ", $resetToken); // <- DEBUG : voir le token

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, TranslatorInterface $translator, ?string $token = null): Response
    {
        if ($token) {
            $this->storeTokenInSession($token);
            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (!$token) {
            dd("Aucun token trouvé en session"); // <- DEBUG
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            dd("Erreur validation token : ", $e->getReason()); // <- DEBUG
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();
            $this->cleanSessionAfterReset();

            return $this->redirectToRoute('app_home');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }

    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer, TranslatorInterface $translator): RedirectResponse
    {
        // dd("Début processSendingPasswordResetEmail avec email : $emailFormData"); // <- DEBUG

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $emailFormData]);
        dd('ici'); // <- DEBUG : on voit si on entre dans la méthode
        dd("Utilisateur trouvé : ", $user); // <- DEBUG

        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
            dd("Token généré : ", $resetToken); // <- DEBUG
        } catch (ResetPasswordExceptionInterface $e) {
            dd("Erreur génération token : ", $e->getReason()); // <- DEBUG
        }

        $email = (new TemplatedEmail())
            ->from(new Address('contact@mrveto.fr', 'MrVeto'))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context(['resetToken' => $resetToken]);

        try {
            $mailer->send($email);
            dd("Mail envoyé avec succès !");
        } catch (\Exception $e) {
            dd("Erreur envoi mail : ", $e->getMessage());
        }

        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }
}
