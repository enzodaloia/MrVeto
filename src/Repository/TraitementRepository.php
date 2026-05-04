<?php

namespace App\Repository;

use App\Entity\Animal;
use App\Entity\Traitement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Traitement>
 */
class TraitementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Traitement::class);
    }

    /**
     * @return Traitement[]
     */
    public function findByAnimal(Animal $animal): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.veterinaire', 'v')->addSelect('v')
            ->andWhere('t.animal = :animal')
            ->setParameter('animal', $animal)
            ->orderBy('t.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
