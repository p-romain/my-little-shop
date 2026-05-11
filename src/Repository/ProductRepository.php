<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @param int[] $shopIds
     */
    public function listQueryBuilder(array $shopIds): QueryBuilder
    {
        $qb = $this
            ->createQueryBuilder('p')
            ->orderBy('p.id', 'ASC')
        ;

        if ([] === $shopIds) {
            return $qb;
        }

        $sub = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('1')
            ->from(Stock::class, 's')
            ->where('s.product = p')
            ->andWhere('s.shop IN (:shopIds)')
            ->andWhere('s.quantity > 0')
            ->getDQL()
        ;

        return $qb
            ->where("EXISTS ($sub)")
            ->setParameter('shopIds', $shopIds)
        ;
    }
}
