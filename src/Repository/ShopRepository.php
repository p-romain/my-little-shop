<?php

namespace App\Repository;

use App\Dto\ShopListQuery;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final class ShopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shop::class);
    }

    public function listQueryBuilder(ShopListQuery $shopListQuery): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s');

        if (null !== $shopListQuery->name) {
            $qb
                ->andWhere('LOWER(s.name) LIKE LOWER(:name)')
                ->setParameter('name', '%'.$shopListQuery->name.'%')
            ;
        }

        if (null !== $shopListQuery->latitude) {
            $this->filterByDistance($qb, $shopListQuery->latitude, $shopListQuery->longitude, $shopListQuery->radius, true);
        } else {
            $qb->orderBy('s.id', 'DESC');
        }

        return $qb;
    }

    private function filterByDistance(QueryBuilder $qb, float $latitude, ?float $longitude, ?int $radius, bool $orderByDistance = false): void
    {
        $distanceExpr = '(
            6371000 * ACOS(
                COS(RADIANS(:latitude))
                    * COS(RADIANS(s.latitude))
                    * COS(RADIANS(s.longitude) - RADIANS(:longitude))
                + SIN(RADIANS(:latitude))
                    * SIN(RADIANS(s.latitude))
            )
        )';

        $qb
            ->andWhere('s.latitude IS NOT NULL')
            ->andWhere('s.longitude IS NOT NULL')
            ->andWhere($distanceExpr.' <= :radius')
            ->setParameter('latitude', $latitude)
            ->setParameter('longitude', $longitude)
            ->setParameter('radius', $radius)
        ;

        if (true === $orderByDistance) {
            $qb
                ->addSelect($distanceExpr.' as distance')
                ->orderBy('distance', 'ASC')
            ;
        }
    }
}
