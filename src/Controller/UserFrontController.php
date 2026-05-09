<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user')]
final class UserFrontController extends AbstractController
{
    #[Route(name: 'app_user_front_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user_front/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_user_front_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_user_front_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user_front/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{slug}', name: 'app_user_front_show', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] User $user): Response
    {
        // Rediriger vers le profil vétérinaire si l'utilisateur a le rôle ROLE_VETO
        if (in_array('ROLE_VETO', $user->getRoles())) {
            return $this->redirectToRoute('app_user_front_show_vet', ['slug' => $user->getSlug()]);
        }
        
        return $this->render('user_front/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/veterinaire/{slug}', name: 'app_user_front_show_vet', methods: ['GET'])]
    public function showVet(#[MapEntity(mapping: ['slug' => 'slug'])] User $user): Response
    {
        // Vérifier que l'utilisateur est bien un vétérinaire
        if (!in_array('ROLE_VETO', $user->getRoles())) {
            return $this->redirectToRoute('app_user_front_show', ['slug' => $user->getSlug()]);
        }
        
        return $this->render('user_front/show_vet.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{slug}/update_vet', name: 'app_user_front_update_vet', methods: ['POST'])]
    public function updateVet(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] User $user, EntityManagerInterface $entityManager): JsonResponse
    {
        $authorizationError = $this->validateUserAuthorization($user);
        if ($authorizationError !== null) {
            return $authorizationError;
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
             return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $form = $this->createForm(UserType::class, $user, ['csrf_protection' => false]);
        $form->submit($data, false);

       if ($form->isValid()) {
            $entityManager->flush();
            return new JsonResponse(['status' => 'success'], Response::HTTP_OK);
        }

        $errors = [];
        foreach ($form->getErrors(true, true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse(['error' => implode(', ', $errors)], Response::HTTP_BAD_REQUEST);
    }

    private function validateUserAuthorization(User $user): ?JsonResponse
    {
        if ($this->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
             return new JsonResponse(['error' => 'Access Denied'], Response::HTTP_FORBIDDEN);
        }
        return null;
    }

    #[Route('/{slug}/update_user', name: 'app_user_front_update_user', methods: ['POST'])]
    public function updateUser(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] User $user, EntityManagerInterface $entityManager): JsonResponse
    {
        $authorizationError = $this->validateUserAuthorization($user);
        if ($authorizationError !== null) {
            return $authorizationError;
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
             return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $form = $this->createForm(UserType::class, $user, ['csrf_protection' => false]);
        $form->submit($data, false);

       if ($form->isValid()) {
            $entityManager->flush();
            return new JsonResponse(['status' => 'success'], Response::HTTP_OK);
        }

        $errors = [];
        foreach ($form->getErrors(true, true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse(['error' => implode(', ', $errors)], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/{slug}/edit', name: 'app_user_front_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] User $user, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_user_front_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user_front/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{slug}/archive', name: 'app_user_front_archive', methods: ['POST'])]
    public function archive(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] User $user, EntityManagerInterface $entityManager, Security $security): Response
    {
        if ($this->isCsrfTokenValid('archive'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $isCurrentUser = $this->getUser() === $user;
            
            // Archiver l'utilisateur
            $user->setIsArchived(true);
            $user->setArchivedAt(new \DateTime());
            $entityManager->flush();
            
            if ($isCurrentUser) {
                $security->logout(false);
            }
        }

        return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{slug}', name: 'app_user_front_delete', methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] User $user, EntityManagerInterface $entityManager, Security $security): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            // Vérifier si l'utilisateur supprime son propre compte
            $isCurrentUser = $this->getUser() === $user;
            
            if (in_array('ROLE_VETO', $user->getRoles())) {
                // Pour un vétérinaire, on ne supprime pas complètement pour garder l'historique des documents pendant 20 ans
                $user->setIsArchived(true);
                $user->setArchivedAt(new \DateTime());
                // Eventuellement anonymiser si nécessaire, mais on garde en l'état pour les archives.
            } else {
                $entityManager->remove($user);
            }
            
            $entityManager->flush();
            
            // Si c'est l'utilisateur connecté, le déconnecter
            if ($isCurrentUser) {
                $security->logout(false);
            }
        }

        return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
    }
}
