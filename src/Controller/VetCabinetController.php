<?php

namespace App\Controller;

use App\Entity\Cabinet;
use App\Entity\CabinetUser;
use App\Entity\User;
use App\Form\CabinetType;
use App\Repository\CabinetUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vet/cabinet')]
final class VetCabinetController extends AbstractController
{
    #[Route(name: 'app_vet_cabinet_index', methods: ['GET'])]
    public function index(Request $request, CabinetUserRepository $cabinetUserRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $managedCabinets = $cabinetUserRepository->findManagedCabinets($currentUser);
        $selectedCabinet = $this->resolveSelectedCabinet($managedCabinets, $request->query->getInt('cabinet'));

        $cabinetForm = $this->createForm(CabinetType::class, new Cabinet(), [
            'action' => $this->generateUrl('app_vet_cabinet_create'),
            'method' => 'POST',
            'csrf_token_id' => 'vet_cabinet_create',
        ]);

        return $this->render('vet_cabinet/index.html.twig', [
            'cabinets' => $managedCabinets,
            'selected_cabinet' => $selectedCabinet,
            'cabinet_form' => $cabinetForm,
        ]);
    }

    #[Route('/{id}/users/search', name: 'app_vet_cabinet_user_search', methods: ['GET'])]
    public function searchUsers(Request $request, Cabinet $cabinet, CabinetUserRepository $cabinetUserRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->canManageCabinet($cabinetUserRepository, $cabinet, $currentUser)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gérer ce cabinet.');
        }

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
            $isVeto = in_array('ROLE_VETO', $roles, true);
            $isSecretaryEligible = in_array('ROLE_SECRETARY', $roles, true) || in_array('ROLE_USER', $roles, true);

            $items[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'roles' => $roles,
                'isVeto' => $isVeto,
                'isSecretary' => $isSecretaryEligible,
            ];
        }

        return $this->json(['items' => $items]);
    }

    #[Route('/{id}/edit', name: 'app_vet_cabinet_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Cabinet $cabinet, CabinetUserRepository $cabinetUserRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->canManageCabinet($cabinetUserRepository, $cabinet, $currentUser)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce cabinet.');
        }

        $form = $this->createForm(CabinetType::class, $cabinet, [
            'action' => $this->generateUrl('app_vet_cabinet_edit', ['id' => $cabinet->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Cabinet mis à jour.');
            return $this->redirectToRoute('app_vet_cabinet_index', ['cabinet' => $cabinet->getId()]);
        }

        if ($form->isSubmitted()) {
            $this->addFlash('danger', 'Impossible de modifier ce cabinet.');
        }

        return $this->render('vet_cabinet/edit.html.twig', [
            'cabinet' => $cabinet,
            'form' => $form,
            'assignments' => $cabinetUserRepository->findByCabinetOrdered($cabinet),
        ]);
    }

    #[Route('/create', name: 'app_vet_cabinet_create', methods: ['POST'])]
    public function create(Request $request, CabinetUserRepository $cabinetUserRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $cabinet = new Cabinet();
        $form = $this->createForm(CabinetType::class, $cabinet, [
            'action' => $this->generateUrl('app_vet_cabinet_create'),
            'method' => 'POST',
            'csrf_token_id' => 'vet_cabinet_create',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $assignment = (new CabinetUser())
                ->setCabinet($cabinet)
                ->setUser($currentUser)
                ->setRoleInCabinet(CabinetUser::ROLE_VETERINAIRE);

            try {
                $entityManager->persist($cabinet);
                $entityManager->persist($assignment);
                $entityManager->flush();

                $this->addFlash('success', 'Votre cabinet a été créé.');
            } catch (\Throwable $throwable) {
                $this->addFlash('danger', 'Erreur SQL lors de la création du cabinet: ' . $throwable->getMessage());
            }
        } else {
            $errors = [];
            foreach ($form->getErrors(true, true) as $error) {
                $errors[] = $error->getMessage();
            }

            $this->addFlash('danger', $errors === []
                ? 'Impossible de créer le cabinet.'
                : 'Impossible de créer le cabinet: ' . implode(' | ', $errors)
            );
        }

        return $this->redirectToRoute('app_vet_cabinet_index');
    }

    #[Route('/{id}/assign', name: 'app_vet_cabinet_assign', methods: ['POST'])]
    public function assign(
        Request $request,
        Cabinet $cabinet,
        CabinetUserRepository $cabinetUserRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->canManageCabinet($cabinetUserRepository, $cabinet, $currentUser)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gérer ce cabinet.');
        }

        $csrfToken = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('vet_cabinet_assign_' . $cabinet->getId(), $csrfToken)) {
            $this->addFlash('danger', 'Le token CSRF est invalide.');
            return $this->redirectToRoute('app_vet_cabinet_edit', ['id' => $cabinet->getId()]);
        }

        $userId = (int) $request->request->get('user_id', 0);
        $requestedRole = (string) $request->request->get('role_in_cabinet', '');

        $userToAssign = $entityManager->getRepository(User::class)->find($userId);
        if (!$userToAssign instanceof User) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_vet_cabinet_edit', ['id' => $cabinet->getId()]);
        }

        $roleValidationError = $this->validateAssignmentRoleRules($cabinetUserRepository, $userToAssign, $requestedRole);
        if ($roleValidationError !== null) {
            $this->addFlash('danger', $roleValidationError);
            return $this->redirectToRoute('app_vet_cabinet_edit', ['id' => $cabinet->getId()]);
        }

        if ($cabinetUserRepository->findOneByCabinetAndUser($cabinet, $userToAssign)) {
            $this->addFlash('danger', 'Cet utilisateur est déjà affecté au cabinet.');
            return $this->redirectToRoute('app_vet_cabinet_edit', ['id' => $cabinet->getId()]);
        }

        $assignment = (new CabinetUser())
            ->setCabinet($cabinet)
            ->setUser($userToAssign)
            ->setRoleInCabinet($requestedRole);

        $this->applySecretaryGlobalRole($userToAssign, $requestedRole);

        $entityManager->persist($assignment);
        $entityManager->flush();
        $this->addFlash('success', 'Utilisateur affecté au cabinet.');

        return $this->redirectToRoute('app_vet_cabinet_edit', ['id' => $cabinet->getId()]);
    }

    #[Route('/assignment/{id}/delete', name: 'app_vet_cabinet_assignment_delete', methods: ['POST'])]
    public function deleteAssignment(Request $request, CabinetUser $assignment, CabinetUserRepository $cabinetUserRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VETO');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $cabinet = $assignment->getCabinet();
        if ($cabinet === null || !$this->canManageCabinet($cabinetUserRepository, $cabinet, $currentUser)) {
            throw $this->createAccessDeniedException();
        }

        $assignmentUserId = (int) ($assignment->getUser()?->getId() ?? 0);
        $currentUserId = (int) ($currentUser->getId() ?? 0);
        if ($assignmentUserId > 0 && $assignmentUserId === $currentUserId) {
            $this->addFlash('danger', 'Vous ne pouvez pas vous retirer vous-même du cabinet.');
            return $this->redirectToRoute('app_vet_cabinet_edit', ['id' => $cabinet->getId()]);
        }

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

        return $this->redirectToRoute('app_vet_cabinet_edit', ['id' => $cabinet->getId()]);
    }

    private function canManageCabinet(CabinetUserRepository $cabinetUserRepository, Cabinet $cabinet, User $currentUser): bool
    {
        $managerAssignment = $cabinetUserRepository->findOneByCabinetAndUser($cabinet, $currentUser);

        return $managerAssignment !== null && $managerAssignment->getRoleInCabinet() === CabinetUser::ROLE_VETERINAIRE;
    }

    private function resolveSelectedCabinet(array $managedCabinets, int $requestedCabinetId): ?Cabinet
    {
        if ($managedCabinets === []) {
            return null;
        }

        if ($requestedCabinetId > 0) {
            foreach ($managedCabinets as $managedCabinet) {
                if ($managedCabinet instanceof Cabinet && $managedCabinet->getId() === $requestedCabinetId) {
                    return $managedCabinet;
                }
            }
        }

        $first = $managedCabinets[0] ?? null;
        return $first instanceof Cabinet ? $first : null;
    }

    private function validateAssignmentRoleRules(CabinetUserRepository $cabinetUserRepository, User $user, string $requestedRole): ?string
    {
        $globalRoles = $user->getRoles();

        if ($requestedRole !== CabinetUser::ROLE_VETERINAIRE && $requestedRole !== CabinetUser::ROLE_SECRETAIRE) {
            return 'Rôle cabinet invalide.';
        }

        if ($requestedRole === CabinetUser::ROLE_SECRETAIRE && in_array('ROLE_VETO', $globalRoles, true)) {
            return 'Rôle incompatible avec cet utilisateur.';
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
