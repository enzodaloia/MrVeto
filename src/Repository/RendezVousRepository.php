<?php

namespace App\Repository;

use App\Entity\Animal;
use App\Entity\CabinetUser;
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

    public function vetHasRdvWithAnimal(User $vet, Animal $animal): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.veterinaire = :vet')
            ->andWhere('r.animal = :animal')
            ->setParameter('vet', $vet)
            ->setParameter('animal', $animal)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function secretaryHasAccessToAnimal(User $secretary, Animal $animal): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->innerJoin(CabinetUser::class, 'cuVet', 'WITH', 'cuVet.user = r.veterinaire AND cuVet.roleInCabinet = :vetRole')
            ->innerJoin(CabinetUser::class, 'cuSec', 'WITH', 'cuSec.cabinet = cuVet.cabinet AND cuSec.user = :secretary AND cuSec.roleInCabinet = :secRole')
            ->andWhere('r.animal = :animal')
            ->setParameter('secretary', $secretary)
            ->setParameter('animal', $animal)
            ->setParameter('vetRole', CabinetUser::ROLE_VETERINAIRE)
            ->setParameter('secRole', CabinetUser::ROLE_SECRETAIRE)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Returns all RDVs for a given animal, ordered by date DESC.
     *
     * @return RendezVous[]
     */
    public function findByAnimalForVet(Animal $animal): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.veterinaire', 'v')->addSelect('v')
            ->andWhere('r.animal = :animal')
            ->setParameter('animal', $animal)
            ->orderBy('r.dateHeure', 'DESC')
            ->getQuery()
            ->getResult();
    }
    /**
     * @return RendezVous[]
     */
    public function findUpcomingForVeterinaire(User $vet): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        return $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.animal', 'a')->addSelect('a')
            ->andWhere('r.veterinaire = :vet')
            ->andWhere('r.dateHeure >= :now')
            ->andWhere('r.statut != :cancelled')
            ->setParameter('vet', $vet)
            ->setParameter('now', $now)
            ->setParameter('cancelled', 'annule')
            ->orderBy('r.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{status?: string|null, dateFrom?: \DateTime|null, dateTo?: \DateTime|null, client?: string|null, animal?: string|null} $filters
     * @return RendezVous[]
     */
    public function findHistoryForVeterinaire(User $vet, array $filters = []): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.animal', 'a')->addSelect('a')
            ->andWhere('r.veterinaire = :vet')
            ->setParameter('vet', $vet);

        $qb->andWhere($qb->expr()->orX('r.dateHeure < :now', 'r.statut = :cancelled'))
            ->setParameter('now', $now)
            ->setParameter('cancelled', 'annule');

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '' && $status !== 'all') {
            $qb->andWhere('r.statut = :status')
                ->setParameter('status', $status);
        }

        $dateFrom = $filters['dateFrom'] ?? null;
        if ($dateFrom instanceof \DateTimeInterface) {
            $qb->andWhere('r.dateHeure >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        $dateTo = $filters['dateTo'] ?? null;
        if ($dateTo instanceof \DateTimeInterface) {
            $qb->andWhere('r.dateHeure <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        $client = $filters['client'] ?? null;
        if (is_string($client) && trim($client) !== '') {
            $clientTerm = '%' . strtolower(trim($client)) . '%';
            $qb->andWhere('LOWER(c.nom) LIKE :client OR LOWER(c.prenom) LIKE :client OR LOWER(c.email) LIKE :client')
                ->setParameter('client', $clientTerm);
        }

        $animal = $filters['animal'] ?? null;
        if (is_string($animal) && trim($animal) !== '') {
            $animalTerm = '%' . strtolower(trim($animal)) . '%';
            $qb->andWhere('LOWER(a.nom) LIKE :animal')
                ->setParameter('animal', $animalTerm);
        }

        return $qb->orderBy('r.dateHeure', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function markPastAsTermineForVeterinaire(User $vet, \DateTimeInterface $now): int
    {
        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.statut', ':termine')
            ->where('r.veterinaire = :vet')
            ->andWhere('r.dateHeure < :now')
            ->andWhere('r.statut NOT IN (:excluded)')
            ->setParameter('termine', 'termine')
            ->setParameter('vet', $vet)
            ->setParameter('now', $now)
            ->setParameter('excluded', ['termine', 'annule'])
            ->getQuery()
            ->execute();
    }
}
