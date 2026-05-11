<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Dto\ShopListQuery;
use App\Entity\Shop;
use App\Repository\ShopRepository;
use App\Tests\ApiTestCase;

final class ShopRepositoryTest extends ApiTestCase
{
    public function testListQueryBuilderWithoutFiltersReturnsShopsOrderedByNewestFirst(): void
    {
        $shops = $this->repository()->listQueryBuilder(new ShopListQuery())
            ->setMaxResults(3)
            ->getQuery()
            ->getResult()
        ;

        self::assertCount(3, $shops);
        self::assertContainsOnlyInstancesOf(Shop::class, $shops);
        self::assertGreaterThan($shops[1]->getId(), $shops[0]->getId());
        self::assertGreaterThan($shops[2]->getId(), $shops[1]->getId());
    }

    public function testListQueryBuilderFiltersByNameCaseInsensitive(): void
    {
        $query = new ShopListQuery();
        $query->setName('  PARIS  ');

        $shops = $this->repository()->listQueryBuilder($query)
            ->getQuery()
            ->getResult()
        ;

        self::assertCount(1, $shops);
        self::assertSame('Paris Champs-Elysees', $shops[0]->getName());
    }

    public function testListQueryBuilderFiltersByDistanceAndSelectsDistance(): void
    {
        $query = new ShopListQuery();
        $query->latitude = 48.8566;
        $query->longitude = 2.3522;
        $query->radius = 1_000_000;

        $rows = $this->repository()->listQueryBuilder($query)
            ->getQuery()
            ->getResult()
        ;

        self::assertNotEmpty($rows);
        self::assertIsArray($rows[0]);
        self::assertInstanceOf(Shop::class, $rows[0][0]);
        self::assertArrayHasKey('distance', $rows[0]);
        self::assertSame('Paris Champs-Elysees', $rows[0][0]->getName());
        self::assertLessThan(10_000, (float) $rows[0]['distance']);
    }

    private function repository(): ShopRepository
    {
        return $this->entityManager->getRepository(Shop::class);
    }
}
