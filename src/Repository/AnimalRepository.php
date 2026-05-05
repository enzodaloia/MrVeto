<?php

namespace App\Repository;

use App\Entity\Animal;
use App\Entity\CabinetUser;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Animal>
 */
class AnimalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Animal::class);
    }

    /**
     * Returns all animals that the given vet has (or had) at least one RDV with,
     * optionally filtered by nom / espece / race.
     *
     * @return Animal[]
     */
    public function findByVetWithSearch(User $vet, string $search = ''): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.proprietaire', 'p')->addSelect('p')
            ->where('EXISTS (SELECT r.id FROM App\Entity\RendezVous r WHERE r.animal = a AND r.veterinaire = :vet)')
            ->setParameter('vet', $vet)
            ->orderBy('a.nom', 'ASC');

        if ($search !== '') {
            $qb->andWhere('a.nom LIKE :search OR a.espece LIKE :search OR a.race LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns all animals linked to vets in the same cabinet(s) as the secretary,
     * optionally filtered by nom / espece / race.
     *
     * @return Animal[]
     */
    public function findBySecretaryCabinetWithSearch(User $secretary, string $search = ''): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.proprietaire', 'p')->addSelect('p')
            ->where('EXISTS (
                SELECT r1.id
                FROM App\\Entity\\RendezVous r1
                JOIN App\\Entity\\CabinetUser cuVet WITH cuVet.user = r1.veterinaire AND cuVet.roleInCabinet = :vetRole
                JOIN App\\Entity\\CabinetUser cuSec WITH cuSec.cabinet = cuVet.cabinet AND cuSec.user = :secretary AND cuSec.roleInCabinet = :secRole
                WHERE r1.animal = a
            )')
            ->setParameter('secretary', $secretary)
            ->setParameter('vetRole', CabinetUser::ROLE_VETERINAIRE)
            ->setParameter('secRole', CabinetUser::ROLE_SECRETAIRE)
            ->orderBy('a.nom', 'ASC');

        if ($search !== '') {
            $qb->andWhere('a.nom LIKE :search OR a.espece LIKE :search OR a.race LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
