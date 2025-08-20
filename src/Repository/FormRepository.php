<?php

namespace App\Repository;

use App\Entity\Form;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Form>
 *
 * @method Form|null find($id, $lockMode = null, $lockVersion = null)
 * @method Form|null findOneBy(array $criteria, array $orderBy = null)
 * @method Form[]    findAll()
 * @method Form[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Form::class);
    }

    public function save(Form $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Form $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Compte le nombre de formulaires pour un utilisateur
     */
    public function countByUser(\App\Entity\User $user): int
    {
        $result = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * @return array<string, int>
     */
    public function countFormsByStatusForUser(string $userId): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('f.status, COUNT(f.id) as formsCount')
            ->where('f.user = :userId')
            ->groupBy('f.status')
            ->setParameter('userId', $userId);

        $result = $qb->getQuery()->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int)$row['formsCount'];
        }

        return $counts;
    }

    public function countAllForms(): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

}
