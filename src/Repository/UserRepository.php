<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    //    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    //    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
    /**
     * Returns a QueryBuilder for vets, with optional geo and profile filters.
     * Pass to KnpPaginator so it can handle pagination itself.
     *
     * @param string[] $specialites slugs to filter on (OR on each)
     * @param string[] $animaux     animal values to filter on (OR on each)
     */
    public function createVetsQueryBuilder(
        ?float $lat = null,
        ?float $lon = null,
        ?int $distance = null,
        bool $urgentiste = false,
        array $specialites = [],
        array $animaux = []
    ): \Doctrine\ORM\QueryBuilder {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->andWhere('u.isVerified = :isVerified')
            ->andWhere('u.archivedAt IS NULL')
            ->setParameter('role', '%"ROLE_VETO"%')
            ->setParameter('isVerified', true)
            ->orderBy('u.nom', 'ASC');

        if ($lat !== null && $lon !== null && $distance !== null && $distance > 0) {
            $latPerKm = 1 / 111.0;
            $lonPerKm = 1 / (111.0 * cos(deg2rad($lat)));

            $latMin = $lat - ($distance * $latPerKm);
            $latMax = $lat + ($distance * $latPerKm);
            $lonMin = $lon - ($distance * $lonPerKm);
            $lonMax = $lon + ($distance * $lonPerKm);

            $qb->andWhere('(u.latitude + 0) >= :latMin')
                ->andWhere('(u.latitude + 0) <= :latMax')
                ->andWhere('(u.longitude + 0) >= :lonMin')
                ->andWhere('(u.longitude + 0) <= :lonMax')
                ->setParameter('latMin', $latMin)
                ->setParameter('latMax', $latMax)
                ->setParameter('lonMin', $lonMin)
                ->setParameter('lonMax', $lonMax);
        }

        // Profile-based filters: single LEFT JOIN on vetProfile
        if ($urgentiste || $animaux !== []) {
            $qb->leftJoin('u.vetProfile', 'vp');

            if ($urgentiste) {
                $qb->andWhere('vp.isUrgentiste = :urgentiste')
                    ->setParameter('urgentiste', true);
            }

            if ($animaux !== []) {
                $orX = $qb->expr()->orX();
                foreach ($animaux as $i => $animal) {
                    $param = 'animal_' . $i;
                    $orX->add($qb->expr()->like('vp.animauxAcceptes', ':' . $param));
                    $qb->setParameter($param, '%"' . addslashes($animal) . '"%');
                }
                $qb->andWhere($orX);
            }
        }

        // Specialites: correlated EXISTS to avoid duplicate rows
        if ($specialites !== []) {
            $qb->andWhere(
                'EXISTS (SELECT 1 FROM App\Entity\VetProfile vp_s JOIN vp_s.specialites spec WHERE vp_s.user = u AND spec.slug IN (:specialites))'
            )->setParameter('specialites', $specialites);
        }

        return $qb;
    }

    /**
     * Returns all urgentiste vets that have coordinates, ordered by nom.
     *
     * @return User[]
     */
    public function findAllUrgentistes(): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.vetProfile', 'vp')
            ->addSelect('vp')
            ->andWhere('u.roles LIKE :role')
            ->andWhere('u.isVerified = :isVerified')
            ->andWhere('u.archivedAt IS NULL')
            ->andWhere('vp.isUrgentiste = :urgentiste')
            ->andWhere('u.latitude IS NOT NULL')
            ->andWhere('u.longitude IS NOT NULL')
            ->setParameter('role', '%"ROLE_VETO"%')
            ->setParameter('isVerified', true)
            ->setParameter('urgentiste', true)
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Paginator Returns a Paginator of User objects with ROLE_VETO
     */
    public function findAllVets(int $page = 1, int $limit = 10, ?float $lat = null, ?float $lon = null, ?int $distance = null): Paginator
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->andWhere('u.isVerified = :isVerified')
            ->andWhere('u.archivedAt IS NULL')
            ->setParameter('role', '%"ROLE_VETO"%')
            ->setParameter('isVerified', true)
            ->orderBy('u.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        // Apply Bounding Box filtering if location is provided
        if ($lat !== null && $lon !== null && $distance !== null && $distance > 0) {
            $latPerKm = 1 / 111.0;
            $lonPerKm = 1 / (111.0 * cos(deg2rad($lat)));

            $latMin = $lat - ($distance * $latPerKm);
            $latMax = $lat + ($distance * $latPerKm);
            $lonMin = $lon - ($distance * $lonPerKm);
            $lonMax = $lon + ($distance * $lonPerKm);

            // Adding +0 forces numerical conversion in case the DB stores them as strings
            $qb->andWhere('(u.latitude + 0) >= :latMin')
                ->andWhere('(u.latitude + 0) <= :latMax')
                ->andWhere('(u.longitude + 0) >= :lonMin')
                ->andWhere('(u.longitude + 0) <= :lonMax')
                ->setParameter('latMin', $latMin)
                ->setParameter('latMax', $latMax)
                ->setParameter('lonMin', $lonMin)
                ->setParameter('lonMax', $lonMax);
        }

        $query = $qb->getQuery();

        return new Paginator($query, true);
    }
}
