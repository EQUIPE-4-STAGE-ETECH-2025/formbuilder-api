<?php

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (! $user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->save($user, true);
    }

    /**
     * Récupère le nom du plan actif pour un utilisateur avec une requête optimisée
     */
    public function getPlanNameForUser(User $user): string
    {
        $result = $this->createQueryBuilder('u')
            ->select('p.name as planName')
            ->leftJoin('u.subscriptions', 's')
            ->leftJoin('s.plan', 'p')
            ->where('u = :user')
            ->andWhere('s.status = :activeStatus')
            ->setParameter('user', $user)
            ->setParameter('activeStatus', Subscription::STATUS_ACTIVE)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['planName'] : 'Free';
    }

    public function countNonAdminUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.role != :admin')
            ->setParameter('admin', 'ADMIN')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function getUsersPerMonth(): array
    {
        $sql = "
            SELECT to_char(u.created_at, 'YYYY-MM') AS month, COUNT(u.id) AS count
            FROM users u
            WHERE u.role != :admin
            GROUP BY month
            ORDER BY month ASC
        ";

        $conn = $this->getEntityManager()->getConnection();

        return $conn->executeQuery($sql, ['admin' => 'ADMIN'])->fetchAllAssociative();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUsersByPlan(): array
    {
        $dql = "
            SELECT COALESCE(p.name, 'Free') AS plan, COUNT(DISTINCT u.id) AS count
            FROM App\Entity\User u
            LEFT JOIN App\Entity\Subscription s WITH s.user = u AND s.status = :active
            LEFT JOIN App\Entity\Plan p WITH s.plan = p
            WHERE u.role != 'ADMIN'
            GROUP BY plan
        ";

        return $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('active', Subscription::STATUS_ACTIVE)
            ->getArrayResult();
    }

    /**
     * Récupère tous les utilisateurs avec leurs statistiques avec DQL optimisé
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllWithStats(): array
    {
        // Utiliser DQL pour une meilleure compatibilité et maintenabilité
        $qb = $this->createQueryBuilder('u')
            ->select([
                'u.id',
                'u.firstName',
                'u.lastName',
                'u.email',
                'u.role',
                'u.createdAt',
                'COALESCE(p.name, \'Free\') as planName',
                'COUNT(DISTINCT f.id) as formsCount',
                'COUNT(DISTINCT s.id) as submissionsCount',
            ])
            ->leftJoin('u.subscriptions', 'sub', 'WITH', 'sub.status = :activeStatus')
            ->leftJoin('sub.plan', 'p')
            ->leftJoin('u.forms', 'f')
            ->leftJoin('f.submissions', 's')
            ->where('u.role != :adminRole')
            ->groupBy('u.id', 'u.firstName', 'u.lastName', 'u.email', 'u.role', 'u.createdAt', 'p.name')
            ->orderBy('u.createdAt', 'DESC')
            ->setParameter('activeStatus', Subscription::STATUS_ACTIVE)
            ->setParameter('adminRole', 'ADMIN');

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Récupère tous les utilisateurs (objets User) avec leurs statistiques en une seule requête optimisée
     *
     * @return array<int, User>
     */
    public function findAllUsersWithStats(): array
    {
        // Récupérer tous les utilisateurs avec leurs relations en une seule requête
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.subscriptions', 'sub', 'WITH', 'sub.status = :activeStatus')
            ->leftJoin('sub.plan', 'p')
            ->leftJoin('u.forms', 'f')
            ->leftJoin('f.submissions', 's')
            ->addSelect('sub', 'p', 'f', 's')
            ->where('u.role != :adminRole')
            ->orderBy('u.createdAt', 'DESC')
            ->setParameter('activeStatus', Subscription::STATUS_ACTIVE)
            ->setParameter('adminRole', 'ADMIN');

        return $qb->getQuery()->getResult();
    }

    /**
     * Supprime tous les logs d'audit liés à un utilisateur
     */
    public function removeUserAuditLogs(User $user): void
    {
        $em = $this->getEntityManager();

        // Supprimer les logs où l'utilisateur est la cible
        foreach ($user->getAuditLogsAsTarget() as $log) {
            $em->remove($log);
        }

        // Supprimer les logs où l'utilisateur est l'admin
        foreach ($user->getAuditLogsAsAdmin() as $log) {
            $em->remove($log);
        }
    }
}
