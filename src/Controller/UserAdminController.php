<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserAdminType;
use App\Repository\UserRepository;
use App\Service\SiretVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/admin/user')]
final class UserAdminController extends AbstractController
{

    #[Route(name: 'app_user_admin_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $user = new User();
        //création utilisateur
        $form = $this->createForm(UserAdminType::class, $user, [
            'is_edit' => false,
        ]);
        return $this->render('user_admin/index.html.twig', [
            'users' => $userRepository->findAll(),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/new', name: 'app_user_admin_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $userPasswordHasher,
        UserRepository $userRepository,
        ResetPasswordHelperInterface $resetPasswordHelper,
        MailerInterface $mailer
    ): Response {
        $user = new User();
        $form = $this->createForm(UserAdminType::class, $user, [
            'is_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $temporaryPassword = bin2hex(random_bytes(32));
                $user->setPassword($userPasswordHasher->hashPassword($user, $temporaryPassword));

                $entityManager->persist($user);
                $entityManager->flush();

                try {
                    $resetToken = $resetPasswordHelper->generateResetToken($user);

                    $email = (new TemplatedEmail())
                        ->from(new Address('contact@mrveto.fr', 'MrVeto'))
                        ->to($user->getEmail())
                        ->subject('Activez votre compte MrVeto')
                        ->htmlTemplate('user_admin/invitation_email.html.twig')
                        ->context([
                            'user' => $user,
                            'resetToken' => $resetToken,
                        ]);

                    $mailer->send($email);
                    $this->addFlash('success', 'Utilisateur créé avec succès. Un email d’invitation a été envoyé.');
                    $this->addFlash('success', 'Email invitation envoyé à ' . $user->getEmail());
                } catch (\Throwable) {
                    $this->addFlash('danger', 'Utilisateur créé, mais l’email d’invitation n’a pas pu être envoyé.');
                }

                return $this->redirectToRoute('app_user_admin_index', [], Response::HTTP_SEE_OTHER);
            }

            foreach ($form->getErrors(true) as $error) {
                if (str_contains(strtolower($error->getMessage()), 'email')) {
                    $this->addFlash('danger', 'Erreur : Cette adresse email est déjà utilisée.');
                    break;
                }
                $this->addFlash('danger', 'Erreur du formulaire : ' . $error->getMessage());
            }

            return $this->render('user_admin/index.html.twig', [
                'users' => $userRepository->findAll(),
                'form' => $form->createView(),
                'openCreateModal' => true,
            ]);
        }

        return $this->render('user_admin/new.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_admin_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        SiretVerificationService $siretVerificationService
    ): Response {
        $form = $this->createForm(UserAdminType::class, $user, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('app_user_admin_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user_admin/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'siretVerification' => $this->buildSiretVerification($user, $siretVerificationService),
        ]);
    }

    /**
     * Active ou désactive la validation admin d'un compte vétérinaire.
     */
    #[Route('/{id}/admin-validation/{value}', name: 'app_user_admin_verify', methods: ['POST'])]
    public function verify(
        User $user,
        int $value,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('verify' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if (!in_array('ROLE_VETO', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Seuls les comptes vétérinaires peuvent être validés par un administrateur.');
        }

        $user->setIsVerified($value === 1);
        $entityManager->flush();

        $this->addFlash('success', $value === 1 ? 'Compte vétérinaire validé.' : 'Validation admin retirée.');
        return $this->redirectToRoute('app_user_admin_index');
    }


    #[Route('/{id}', name: 'app_user_admin_show', methods: ['GET'])]
    public function show(Request $request, User $user, SiretVerificationService $siretVerificationService): Response
    {
        $siretVerification = $this->buildSiretVerification($user, $siretVerificationService);

        if ($request->query->get('modal') === '1') {
            return $this->render('user_admin/_show_content.html.twig', [
                'user' => $user,
                'siretVerification' => $siretVerification,
            ]);
        }

        return $this->render('user_admin/show.html.twig', [
            'user' => $user,
            'siretVerification' => $siretVerification,
        ]);
    }

    #[Route('/{id}', name: 'app_user_admin_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $resetPasswordRequests = $entityManager
                ->getRepository(\App\Entity\ResetPasswordRequest::class)
                ->findBy(['user' => $user]);

            foreach ($resetPasswordRequests as $resetPasswordRequest) {
                $entityManager->remove($resetPasswordRequest);
            }

            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('danger', 'Utilisateur supprimé.');
        }

        return $this->redirectToRoute('app_user_admin_index', [], Response::HTTP_SEE_OTHER);
    }

    private function buildSiretVerification(User $user, SiretVerificationService $siretVerificationService): ?array
    {
        if (!in_array('ROLE_VETO', $user->getRoles(), true)) {
            return null;
        }

        return $siretVerificationService->verify($user->getSiret());
    }
}
