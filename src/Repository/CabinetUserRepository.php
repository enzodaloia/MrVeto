<?php

namespace App\Repository;

use App\Entity\Cabinet;
use App\Entity\CabinetUser;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CabinetUser>
 */
class CabinetUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CabinetUser::class);
    }

    public function findOneByCabinetAndUser(Cabinet $cabinet, User $user): ?CabinetUser
    {
        return $this->findOneBy([
            'cabinet' => $cabinet,
            'user' => $user,
        ]);
    }

    /**
     * @return CabinetUser[]
     */
    public function findByCabinetOrdered(Cabinet $cabinet): array
    {
        return $this->createQueryBuilder('cu')
            ->leftJoin('cu.user', 'u')
            ->addSelect('u')
            ->where('cu.cabinet = :cabinet')
            ->setParameter('cabinet', $cabinet)
            ->orderBy('cu.roleInCabinet', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCabinetForUserRole(User $user, string $roleInCabinet): ?Cabinet
    {
        $assignment = $this->createQueryBuilder('cu')
            ->leftJoin('cu.cabinet', 'c')
            ->addSelect('c')
            ->where('cu.user = :user')
            ->andWhere('cu.roleInCabinet = :role')
            ->setParameter('user', $user)
            ->setParameter('role', $roleInCabinet)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $assignment?->getCabinet();
    }

    /**
     * @return Cabinet[]
     */
    public function findManagedCabinets(User $user): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('c')
            ->from(Cabinet::class, 'c')
            ->innerJoin(CabinetUser::class, 'cu', 'WITH', 'cu.cabinet = c')
            ->where('cu.user = :user')
            ->andWhere('cu.roleInCabinet = :role')
            ->setParameter('user', $user)
            ->setParameter('role', CabinetUser::ROLE_VETERINAIRE)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
     */
    public function searchAssignableUsersByEmail(Cabinet $cabinet, string $emailQuery, int $limit = 10): array
    {
        $normalized = mb_strtolower(trim($emailQuery));
        if ($normalized === '') {
            return [];
        }

        return $this->getEntityManager()->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('LOWER(u.email) LIKE :email')
            ->andWhere('(u.roles LIKE :vetoRole OR u.roles LIKE :secretaryRole OR u.roles LIKE :secretaryLegacyRole OR u.roles LIKE :userRole)')
            ->andWhere('NOT EXISTS (
                SELECT 1
                FROM App\\Entity\\CabinetUser cuAssigned
                WHERE cuAssigned.user = u
                AND (
                    cuAssigned.roleInCabinet = :veterinaireCabinetRole
                    OR cuAssigned.roleInCabinet = :secretaryCabinetRole
                )
            )')
            ->setParameter('email', '%' . $normalized . '%')
            ->setParameter('vetoRole', '%"ROLE_VETO"%')
            ->setParameter('secretaryRole', '%"ROLE_SECRETARY"%')
            ->setParameter('secretaryLegacyRole', '%"ROLE_SECRETAIRE"%')
            ->setParameter('userRole', '%"ROLE_USER"%')
            ->setParameter('veterinaireCabinetRole', CabinetUser::ROLE_VETERINAIRE)
            ->setParameter('secretaryCabinetRole', CabinetUser::ROLE_SECRETAIRE)
            ->orderBy('u.email', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function hasSecretaryAssignment(User $user): bool
    {
        return $this->createQueryBuilder('cu')
            ->select('COUNT(cu.id)')
            ->where('cu.user = :user')
            ->andWhere('cu.roleInCabinet = :role')
            ->setParameter('user', $user)
            ->setParameter('role', CabinetUser::ROLE_SECRETAIRE)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * @return User[]
     */
    public function findUsersByCabinetRole(Cabinet $cabinet, string $roleInCabinet): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->innerJoin(CabinetUser::class, 'cu', 'WITH', 'cu.user = u')
            ->where('cu.cabinet = :cabinet')
            ->andWhere('cu.roleInCabinet = :role')
            ->setParameter('cabinet', $cabinet)
            ->setParameter('role', $roleInCabinet)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
