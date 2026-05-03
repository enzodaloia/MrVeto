<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserAdminType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
        UserRepository $userRepository
    ): Response {
        $user = new User();
        $form = $this->createForm(UserAdminType::class, $user, [
            'is_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $plainPassword = $form->get('password')->getData();

                if ($plainPassword) {
                    $hashedPassword = $userPasswordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Utilisateur créé avec succès.');

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

    // Debug temporaire (à supprimer
    /**if ($form->isSubmitted() && !$form->isValid()) {
        dd($form->getErrors(true, true));
    }

    return $this->render('user_admin/new.html.twig', [
        'user' => $user,
        'form' => $form->createView(),
    ]);
}**/

    #[Route('/{id}/edit', name: 'app_user_admin_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $userPasswordHasher
    ): Response {
        $form = $this->createForm(UserAdminType::class, $user, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {


            if ($form->has('password')) {
                $plainPassword = $form->get('password')->getData();

                if ($plainPassword) {
                    $hashedPassword = $userPasswordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('app_user_admin_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user_admin/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Active ou désactive la validation d’un utilisateur
     * - Vérification CSRF obligatoire
     */
    #[Route('/admin/user/{id}/verify/{value}', name: 'app_user_admin_verify', methods: ['POST'])]
    public function verify(
        User $user,
        int $value,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Sécurité CSRF
        if (!$this->isCsrfTokenValid('verify' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // Mise à jour du statut de vérification
        $user->setIsVerified($value === 1);
        $entityManager->flush();

        $this->addFlash('success', $value === 1 ? 'Utilisateur validé.' : 'Validation retirée.');
        return $this->redirectToRoute('app_user_admin_index');
    }


    #[Route('/{id}', name: 'app_user_admin_show', methods: ['GET'])]
    public function show(Request $request, User $user): Response
    {
        if ($request->query->get('modal') === '1') {
            return $this->render('user_admin/_show_content.html.twig', [
                'user' => $user,
            ]);
        }

        return $this->render('user_admin/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}', name: 'app_user_admin_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('danger', 'Utilisateur supprimé.');
        }

        return $this->redirectToRoute('app_user_admin_index', [], Response::HTTP_SEE_OTHER);
    }
}