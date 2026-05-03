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
     * @return Paginator Returns a Paginator of User objects with ROLE_VETO
     */
    public function findAllVets(int $page = 1, int $limit = 10, ?float $lat = null, ?float $lon = null, ?int $distance = null): Paginator
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->andWhere('u.isAdminValidated = :isAdminValidated')
            ->setParameter('role', '%"ROLE_VETO"%')
            ->setParameter('isAdminValidated', true)
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
