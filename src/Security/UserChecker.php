<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (in_array('ROLE_VETO', $user->getRoles(), true) && !$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('VOTRE_COMPTE_EN_ATTENTE');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
