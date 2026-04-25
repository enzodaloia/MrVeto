<?php

namespace App\Repository;

use App\Entity\RendezVous;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RendezVous>
 */
class RendezVousRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RendezVous::class);
    }

    /**
     * @return RendezVous[]
     */
    public function findUpcomingWithinDaysForClient(User $client, int $days = 7): array
    {
        $now = new \DateTime();
        $until = (clone $now)->modify(sprintf('+%d days', $days));

        return $this->createQueryBuilder('r')
            ->andWhere('r.client = :client')
            ->andWhere('r.dateHeure >= :now')
            ->andWhere('r.dateHeure <= :until')
            ->andWhere('r.statut != :cancelled')
            ->setParameter('client', $client)
            ->setParameter('now', $now)
            ->setParameter('until', $until)
            ->setParameter('cancelled', 'annule')
            ->orderBy('r.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RendezVous[]
     */
    public function findActionNotificationsForClient(User $client, int $daysBack = 30): array
    {
        $since = (new \DateTime())->modify(sprintf('-%d days', $daysBack));

        return $this->createQueryBuilder('r')
            ->andWhere('r.client = :client')
            ->andWhere('r.lastActionAt IS NOT NULL')
            ->andWhere('r.lastActionAt >= :since')
            ->andWhere('r.lastActionType IN (:actionTypes)')
            ->andWhere('r.lastActionByRole IN (:actorRoles)')
            ->setParameter('client', $client)
            ->setParameter('since', $since)
            ->setParameter('actionTypes', [RendezVous::ACTION_TYPE_CANCELLED, RendezVous::ACTION_TYPE_RESCHEDULED])
            ->setParameter('actorRoles', [RendezVous::ACTION_BY_VETERINAIRE, RendezVous::ACTION_BY_SECRETAIRE])
            ->orderBy('r.lastActionAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
