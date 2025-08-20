<?php

namespace App\Repository;

use App\Entity\Submission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Submission>
 *
 * @method Submission|null find($id, $lockMode = null, $lockVersion = null)
 * @method Submission|null findOneBy(array $criteria, array $orderBy = null)
 * @method Submission[]    findAll()
 * @method Submission[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Submission::class);
    }

    public function save(Submission $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Submission $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Compte le nombre de soumissions pour un utilisateur pour un mois donné
     */
    public function countByUserForMonth(\App\Entity\User $user, \DateTime $month): int
    {
        $startOfMonth = clone $month;
        $startOfMonth->setDate((int) $month->format('Y'), (int) $month->format('n'), 1);
        $startOfMonth->setTime(0, 0, 0);

        $endOfMonth = clone $startOfMonth;
        $endOfMonth->modify('+1 month');

        $result = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.form', 'f')
            ->andWhere('f.user = :user')
            ->andWhere('s.submittedAt >= :startOfMonth')
            ->andWhere('s.submittedAt < :endOfMonth')
            ->setParameter('user', $user)
            ->setParameter('startOfMonth', $startOfMonth)
            ->setParameter('endOfMonth', $endOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function countByUserForms(string $userId): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.form', 'f')
            ->where('f.user = :userId')
            ->setParameter('userId', $userId);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array<int|string, mixed>
     * @throws Exception
     */
    public function countSubmissionsPerMonthByUser(string $userId): array
    {
        $sql = "
        SELECT to_char(s.submitted_at, 'YYYY-MM') AS month, COUNT(s.id) AS submissionsCount
        FROM submission s
        INNER JOIN form f ON s.form_id = f.id
        WHERE f.user_id = :userId
        GROUP BY month
        ORDER BY month ASC
    ";

        return $this->getEntityManager()->getConnection()
            ->executeQuery($sql, ['userId' => $userId])
            ->fetchAllKeyValue();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function countSubmissionsPerFormByUser(string $userId): array
    {
        $sql = "
        SELECT f.title AS formTitle, COUNT(s.id) AS submissionsCount
        FROM submission s
        INNER JOIN form f ON s.form_id = f.id
        WHERE f.user_id = :userId
        GROUP BY f.title
        ORDER BY submissionsCount DESC
    ";

        return $this->getEntityManager()->getConnection()
            ->executeQuery($sql, ['userId' => $userId])
            ->fetchAllKeyValue();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentSubmissionsByUser(string $userId, int $limit = 3): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.id, s.submittedAt, f.title AS formTitle')
            ->join('s.form', 'f')
            ->where('f.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('s.submittedAt', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getArrayResult();
    }

    public function countAllSubmissions(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
