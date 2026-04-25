<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\NotificationService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private Security $security,
        private NotificationService $notificationService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('navbar_notifications', [$this, 'getNavbarNotifications']),
        ];
    }

    /**
     * @return array<int, array{type: string, title: string, message: string, date: \DateTime}>
     */
    public function getNavbarNotifications(int $limit = 10): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->notificationService->getNavbarNotifications($user, $limit);
    }
}
