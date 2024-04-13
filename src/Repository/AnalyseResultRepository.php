<?php

namespace App\Repository;

use App\Entity\AnalyseResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnalyseResult>
 *
 * @method AnalyseResult|null find($id, $lockMode = null, $lockVersion = null)
 * @method AnalyseResult|null findOneBy(array $criteria, array $orderBy = null)
 * @method AnalyseResult[]    findAll()
 * @method AnalyseResult[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AnalyseResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyseResult::class);
    }

    //    /**
    //     * @return AnalyseResult[] Returns an array of AnalyseResult objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?AnalyseResult
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
