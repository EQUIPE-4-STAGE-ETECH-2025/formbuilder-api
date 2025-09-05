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
     * Récupère le nom du plan actif pour un utilisateur
     */
    public function getPlanNameForUser(User $user): string
    {
        $activeSubscription = $user->getSubscriptions()
            ->filter(fn (Subscription $s): bool => $s->getStatus() === Subscription::STATUS_ACTIVE)
            ->last();

        return $activeSubscription && $activeSubscription->getPlan()
            ? $activeSubscription->getPlan()->getName() ?? 'Free'
            : 'Free';
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
     * Récupère tous les utilisateurs avec leurs statistiques en une seule requête
     * pour éviter le problème N+1 et les doublons
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllWithStats(): array
    {
        $sql = "
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.role,
                u.created_at,
                COALESCE(p.name, 'Free') AS plan_name,
                COUNT(DISTINCT f.id) AS forms_count,
                COUNT(DISTINCT s.id) AS submissions_count
            FROM users u
            LEFT JOIN (
                SELECT sub1.*
                FROM subscription sub1
                INNER JOIN (
                    SELECT user_id, MAX(created_at) AS max_created
                    FROM subscription
                    WHERE status = 'ACTIVE'
                    GROUP BY user_id
                ) sub2 ON sub1.user_id = sub2.user_id AND sub1.created_at = sub2.max_created
            ) sub ON u.id = sub.user_id
            LEFT JOIN plan p ON sub.plan_id = p.id
            LEFT JOIN form f ON u.id = f.user_id
            LEFT JOIN submission s ON f.id = s.form_id
            WHERE u.role != 'ADMIN'
            GROUP BY u.id, u.first_name, u.last_name, u.email, u.role, u.created_at, p.name
            ORDER BY u.created_at DESC
        ";

        $conn = $this->getEntityManager()->getConnection();
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }
}
