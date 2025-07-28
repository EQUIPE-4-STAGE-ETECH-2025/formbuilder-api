<?php

namespace App\Repository;

use App\Entity\FormVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormVersion>
 *
 * @method FormVersion|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormVersion|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormVersion[]    findAll()
 * @method FormVersion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormVersion::class);
    }

    public function save(FormVersion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FormVersion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
} 