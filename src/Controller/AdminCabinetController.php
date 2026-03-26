<?php

namespace App\Controller;

use App\Entity\Cabinet;
use App\Entity\CabinetUser;
use App\Entity\User;
use App\Form\CabinetAssignmentType;
use App\Form\CabinetType;
use App\Repository\CabinetRepository;
use App\Repository\CabinetUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/cabinet')]
final class AdminCabinetController extends AbstractController
{
    #[Route(name: 'app_admin_cabinet_index', methods: ['GET'])]
    public function index(CabinetRepository $cabinetRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin_cabinet/index.html.twig', [
            'cabinets' => $cabinetRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_admin_cabinet_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $cabinet = new Cabinet();
        $form = $this->createForm(CabinetType::class, $cabinet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($cabinet);
            $entityManager->flush();

            $this->addFlash('success', 'Cabinet créé avec succès.');

            return $this->redirectToRoute('app_admin_cabinet_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin_cabinet/new.html.twig', [
            'cabinet' => $cabinet,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_cabinet_show', methods: ['GET'])]
    public function show(Cabinet $cabinet, CabinetUserRepository $cabinetUserRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin_cabinet/show.html.twig', [
            'cabinet' => $cabinet,
            'assignments' => $cabinetUserRepository->findByCabinetOrdered($cabinet),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_cabinet_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Cabinet $cabinet, CabinetUserRepository $cabinetUserRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(CabinetType::class, $cabinet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Cabinet mis à jour.');

            return $this->redirectToRoute('app_admin_cabinet_edit', ['id' => $cabinet->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin_cabinet/edit.html.twig', [
            'cabinet' => $cabinet,
            'form' => $form,
            'assignments' => $cabinetUserRepository->findByCabinetOrdered($cabinet),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_cabinet_delete', methods: ['POST'])]
    public function delete(Request $request, Cabinet $cabinet, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete_cabinet_' . $cabinet->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($cabinet);
            $entityManager->flush();
            $this->addFlash('success', 'Cabinet supprimé.');
        }

        return $this->redirectToRoute('app_admin_cabinet_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/assign', name: 'app_admin_cabinet_assign', methods: ['POST'])]
    public function assignUser(
        Request $request,
        Cabinet $cabinet,
        CabinetUserRepository $cabinetUserRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $directUserId = (int) $request->request->get('user_id', 0);
        $directRole = (string) $request->request->get('role_in_cabinet', '');

        if ($directUserId > 0 && $directRole !== '') {
            $csrfToken = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('admin_cabinet_assign_' . $cabinet->getId(), $csrfToken)) {
                $this->addFlash('danger', 'Le token CSRF est invalide.');
                return $this->redirectToRoute('app_admin_cabinet_edit', ['id' => $cabinet->getId()]);
            }

            $user = $entityManager->getRepository(User::class)->find($directUserId);
            if (!$user instanceof User) {
                $this->addFlash('danger', 'Utilisateur invalide.');
                return $this->redirectToRoute('app_admin_cabinet_edit', ['id' => $cabinet->getId()]);
            }

            $validationError = $this->validateAssignmentRoleRules($cabinetUserRepository, $user, $directRole);
            if ($validationError !== null) {
                $this->addFlash('danger', $validationError);
                return $this->redirectToRoute('app_admin_cabinet_edit', ['id' => $cabinet->getId()]);
            }

            if ($cabinetUserRepository->findOneByCabinetAndUser($cabinet, $user)) {
                $this->addFlash('danger', 'Cet utilisateur est déjà affecté à ce cabinet.');
                return $this->redirectToRoute('app_admin_cabinet_edit', ['id' => $cabinet->getId()]);
            }

            $assignment = (new CabinetUser())
                ->setCabinet($cabinet)
                ->setUser($user)
                ->setRoleInCabinet($directRole);

            $this->applySecretaryGlobalRole($user, $directRole);

            $entityManager->persist($assignment);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur affecté au cabinet.');
            return $this->redirectToRoute('app_admin_cabinet_edit', ['id' => $cabinet->getId()]);
        }

        $assignment = new CabinetUser();
        $form = $this->createForm(CabinetAssignmentType::class, $assignment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $assignment->getUser();

            if ($user === null) {
                $this->addFlash('danger', 'Utilisateur invalide.');
                return $this->redirectToRoute('app_admin_cabinet_edit', ['id' => $cabinet->getId()]);
            }

            $validationError = $this->validateAssignmentRoleRules($cabinetUserRepository, $user, (string) $assignment->getRoleInCabinet());
            if ($validationError !== null) {
                $this->addFlash('danger', $validationError);
                return $this->redirectToRoute('app_admin_cabinet_edit', ['id' => $cabinet->getId()]);
            }

            if ($cabinetUserRepository->findOneByCabinetAndUser($cabinet, $user)) {
                $this->addFlash('danger', 'Cet utilisateur est déjà affecté à ce cabinet.');
                return $this->redirectToRoute('app_admin_cabinet_edit', ['id' => $cabinet->getId()]);
            }

            $assignment->setCabinet($cabinet);
            $this->applySecretaryGlobalRole($user, (string) $assignment->getRoleInCabinet());
            $entityManager->persist($assignment);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur affecté au cabinet.');
        }

        return $this->redirectToRoute('app_admin_cabinet_edit', ['id' => $cabinet->getId()]);
    }

    #[Route('/{id}/users/search', name: 'app_admin_cabinet_user_search', methods: ['GET'])]
    public function searchUsers(Request $request, Cabinet $cabinet, CabinetUserRepository $cabinetUserRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $emailQuery = trim((string) $request->query->get('q', ''));
        if ($emailQuery === '') {
            return $this->json(['items' => []]);
        }

        $users = $cabinetUserRepository->searchAssignableUsersByEmail($cabinet, $emailQuery);

        $items = [];
        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $roles = $user->getRoles();
            $items[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'roles' => $roles,
                'isVeto' => in_array('ROLE_VETO', $roles, true),
                'isSecretary' => in_array('ROLE_SECRETARY', $roles, true) || in_array('ROLE_USER', $roles, true),
            ];
        }

        return $this->json(['items' => $items]);
    }

    #[Route('/assignment/{id}/delete', name: 'app_admin_cabinet_assignment_delete', methods: ['POST'])]
    public function deleteAssignment(
        Request $request,
        CabinetUser $assignment,
        CabinetUserRepository $cabinetUserRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $currentUser = $this->getUser();
        $assignmentUserId = (int) ($assignment->getUser()?->getId() ?? 0);
        $currentUserId = $currentUser instanceof User ? (int) ($currentUser->getId() ?? 0) : 0;
        if ($currentUserId > 0 && $assignmentUserId > 0 && $assignmentUserId === $currentUserId) {
            $cabinetId = $assignment->getCabinet()?->getId();
            $this->addFlash('danger', 'Vous ne pouvez pas vous retirer vous-même du cabinet.');

            if ($cabinetId === null) {
                return $this->redirectToRoute('app_admin_cabinet_index');
            }

            return $this->redirectToRoute('app_admin_cabinet_edit', ['id' => $cabinetId]);
        }

        $cabinetId = $assignment->getCabinet()?->getId();
        $assignedUser = $assignment->getUser();
        $wasSecretaryAssignment = $assignment->getRoleInCabinet() === CabinetUser::ROLE_SECRETAIRE;

        $csrfToken = (string) $request->request->get('_token', '');
        if ($this->isCsrfTokenValid('delete_assignment_' . $assignment->getId(), $csrfToken)) {
            $entityManager->remove($assignment);
            $entityManager->flush();

            if ($wasSecretaryAssignment && $assignedUser instanceof User) {
                $this->restoreUserRoleIfSecretaryRemoved($assignedUser, $cabinetUserRepository);
                $entityManager->flush();
            }

            $this->addFlash('success', 'Affectation supprimée.');
        } else {
            $this->addFlash('danger', 'Impossible de supprimer l\'affectation (token invalide).');
        }

        if ($cabinetId === null) {
            return $this->redirectToRoute('app_admin_cabinet_index');
        }

        return $this->redirectToRoute('app_admin_cabinet_edit', ['id' => $cabinetId]);
    }

    private function validateAssignmentRoleRules(CabinetUserRepository $cabinetUserRepository, User $user, string $requestedRole): ?string
    {
        $globalRoles = $user->getRoles();

        if ($requestedRole !== CabinetUser::ROLE_VETERINAIRE && $requestedRole !== CabinetUser::ROLE_SECRETAIRE) {
            return 'Rôle cabinet invalide.';
        }

        if ($requestedRole === CabinetUser::ROLE_SECRETAIRE && in_array('ROLE_VETO', $globalRoles, true)) {
            return 'Un vétérinaire ne peut pas être ajouté comme secrétaire.';
        }

        if ($requestedRole === CabinetUser::ROLE_VETERINAIRE && !in_array('ROLE_VETO', $globalRoles, true)) {
            return 'Cet utilisateur n\'a pas le rôle global vétérinaire.';
        }

        if ($requestedRole === CabinetUser::ROLE_SECRETAIRE) {
            $canBeSecretary = in_array('ROLE_SECRETARY', $globalRoles, true) || in_array('ROLE_USER', $globalRoles, true);
            if (!$canBeSecretary) {
                return 'Cet utilisateur ne peut pas être ajouté comme secrétaire.';
            }

            if ($cabinetUserRepository->hasSecretaryAssignment($user)) {
                return 'Une secrétaire ne peut pas être affectée à plusieurs cabinets.';
            }
        }

        return null;
    }

    private function applySecretaryGlobalRole(User $user, string $requestedRole): void
    {
        if ($requestedRole !== CabinetUser::ROLE_SECRETAIRE) {
            return;
        }

        $roles = $user->getRoles();
        if (!in_array('ROLE_SECRETARY', $roles, true)) {
            $roles[] = 'ROLE_SECRETARY';
        }

        $user->setRoles($roles);
    }

    private function restoreUserRoleIfSecretaryRemoved(User $user, CabinetUserRepository $cabinetUserRepository): void
    {
        if ($cabinetUserRepository->hasSecretaryAssignment($user)) {
            return;
        }

        $roles = array_values(array_diff($user->getRoles(), ['ROLE_SECRETARY', 'ROLE_SECRETAIRE']));
        $roles[] = 'ROLE_USER';
        $user->setRoles(array_values(array_unique($roles)));
    }
}
