<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 *
 * @method AuditLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method AuditLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method AuditLog[]    findAll()
 * @method AuditLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function save(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentAdminActions(int $limit = 3): array
    {
        return $this->createQueryBuilder('al')
            ->select('al.action, al.reason, al.createdAt, tu.email AS targetEmail')
            ->join('al.targetUser', 'tu')
            ->join('al.admin', 'au')
            ->where('au.role = :role')
            ->setParameter('role', 'ADMIN')
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }
}
