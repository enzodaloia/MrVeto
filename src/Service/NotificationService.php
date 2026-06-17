<?php

namespace App\Service;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Repository\RendezVousRepository;

class NotificationService
{
    public function __construct(private RendezVousRepository $rendezVousRepository)
    {
    }

    /**
     * @return array<int, array{type: string, title: string, message: string, date: \DateTime}>
     */
    public function getNavbarNotifications(User $user, int $limit = 10): array
    {
        $notifications = [];

        foreach ($this->rendezVousRepository->findActionNotificationsForClient($user) as $rdv) {
            $lastActionType = $rdv->getLastActionType();
            $lastActionAt = $rdv->getLastActionAt();

            if ($lastActionAt === null || $lastActionType === null) {
                continue;
            }

            if ($lastActionType === RendezVous::ACTION_TYPE_CANCELLED) {
                $notifications[] = [
                    'type' => 'cancelled',
                    'title' => 'Rendez-vous annulé par le cabinet',
                    'message' => sprintf(
                        'Le rendez-vous prévu le %s avec Dr. %s a été annulé.',
                        $this->formatDateHeure($rdv->getDateHeure()),
                        $rdv->getVeterinaire()?->getNom() ?? 'vétérinaire'
                    ),
                    'date' => $lastActionAt,
                ];

                continue;
            }

            if ($lastActionType === RendezVous::ACTION_TYPE_RESCHEDULED) {
                $oldDate = $rdv->getPreviousDateHeure();

                $message = sprintf(
                    'Votre rendez-vous avec Dr. %s a été déplacé au %s.',
                    $rdv->getVeterinaire()?->getNom() ?? 'vétérinaire',
                    $this->formatDateHeure($rdv->getDateHeure())
                );

                if ($oldDate !== null) {
                    $message = sprintf(
                        'Votre rendez-vous avec Dr. %s a été déplacé du %s au %s.',
                        $rdv->getVeterinaire()?->getNom() ?? 'vétérinaire',
                        $this->formatDateHeure($oldDate),
                        $this->formatDateHeure($rdv->getDateHeure())
                    );
                }

                $notifications[] = [
                    'type' => 'rescheduled',
                    'title' => 'Rendez-vous déplacé par le cabinet',
                    'message' => $message,
                    'date' => $lastActionAt,
                ];
            }
        }

        foreach ($this->rendezVousRepository->findUpcomingWithinDaysForClient($user, 7) as $rdv) {
            $notifications[] = [
                'type' => 'upcoming',
                'title' => 'Rendez-vous bientôt',
                'message' => sprintf(
                    'Le %s avec Dr. %s.',
                    $this->formatDateHeure($rdv->getDateHeure()),
                    $rdv->getVeterinaire()?->getNom() ?? 'vétérinaire'
                ),
                'date' => $rdv->getDateHeure(),
            ];
        }

        $now = new \DateTime();
        $twoMonthsLater = (clone $now)->modify('+2 months');

        foreach ($user->getAnimals() as $animal) {
            $vaccinDate = $animal->getProchainVaccin();

            if ($vaccinDate === null) {
                $notifications[] = [
                    'type' => 'vaccin',
                    'title' => 'Rappel de vaccin',
                    'message' => sprintf(
                        'Le vaccin de %s est à mettre à jour.',
                        $animal->getNom()
                    ),
                    'date' => clone $now,
                ];
            } else {
                $diff = $now->diff($vaccinDate);
                $days = $diff->days;
                $isInFuture = !$diff->invert;

                if (!$isInFuture) {
                    $notifications[] = [
                        'type' => 'vaccin',
                        'title' => 'Vaccin en retard',
                        'message' => sprintf(
                            'Le vaccin de %s aurait dû être fait le %s.',
                            $animal->getNom(),
                            $vaccinDate->format('d/m/Y')
                        ),
                        'date' => clone $now,
                    ];
                } elseif ($days <= 60) {
                    $notifications[] = [
                        'type' => 'vaccin',
                        'title' => 'Rappel de vaccin',
                        'message' => sprintf(
                            'Le vaccin de %s est à faire pour le %s.',
                            $animal->getNom(),
                            $vaccinDate->format('d/m/Y')
                        ),
                        'date' => clone $now,
                    ];
                }
            }
        }

        // Sort notifications by date (descending)
        usort($notifications, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return array_slice($notifications, 0, $limit);
    }

    private function formatDateHeure(?\DateTime $date): string
    {
        if ($date === null) {
            return 'date inconnue';
        }

        return $date->format('d/m/Y \à H\hi');
    }
}
