<?php

namespace App\Repository;

use App\Entity\QuotaStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuotaStatus>
 *
 * @method QuotaStatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method QuotaStatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method QuotaStatus[]    findAll()
 * @method QuotaStatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QuotaStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuotaStatus::class);
    }

    public function save(QuotaStatus $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(QuotaStatus $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
