<?php

namespace App\Repository;

use App\Entity\PlanFeature;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanFeature>
 *
 * @method PlanFeature|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlanFeature|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlanFeature[]    findAll()
 * @method PlanFeature[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlanFeatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanFeature::class);
    }

    public function save(PlanFeature $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PlanFeature $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
} 