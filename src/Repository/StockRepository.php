<?php

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    /**
     * Returns stocks grouped by product ID, only where quantity > 0,
     * with the shop pre-loaded. Designed for bulk product listing.
     *
     * @param int[] $productIds
     * @param int[] $shopIds    When non-empty, restrict to these shops only
     *
     * @return array<int, Stock[]>
     */
    public function findByProductIds(array $productIds, array $shopIds = []): array
    {
        if ([] === $productIds) {
            return [];
        }

        $qb = $this
            ->createQueryBuilder('s')
            ->addSelect('sh')
            ->join('s.shop', 'sh')
            ->where('s.product IN (:ids)')
            ->andWhere('s.quantity > 0')
            ->setParameter('ids', $productIds)
        ;

        if ([] !== $shopIds) {
            $qb
                ->andWhere('s.shop IN (:shopIds)')
                ->setParameter('shopIds', $shopIds)
            ;
        }

        /** @var Stock[] $stocks */
        $stocks = $qb
            ->getQuery()
            ->getResult()
        ;

        $grouped = [];
        foreach ($stocks as $stock) {
            $grouped[$stock->getProduct()->getId()][] = $stock;
        }

        return $grouped;
    }
}
