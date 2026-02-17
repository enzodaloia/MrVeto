<?php

namespace App\Repository;

use App\Entity\Horaire;
namespace App\Repository;

use App\Entity\Horaire;
use App\Entity\DayOfWork;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;



/**
 * @extends ServiceEntityRepository<Horaire>
 */
class HoraireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Horaire::class);
    }

    public function existsOverlapForDayOfWork(
        DayOfWork $dayOfWork,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        ?int $excludeId = null
    ): bool {
        $qb = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.dayOfWork = :dow')
            ->andWhere('h.startTime < :end')
            ->andWhere('h.endTime > :start')
            ->setParameter('dow', $dayOfWork)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($excludeId !== null) {
            $qb->andWhere('h.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    //    /**
    //     * @return Horaire[] Returns an array of Horaire objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('h.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Horaire
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
