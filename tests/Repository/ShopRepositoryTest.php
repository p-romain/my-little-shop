<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Dto\ShopListQuery;
use App\Entity\Shop;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ShopRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ShopRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(Shop::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);

        $this->repository = new ShopRepository($registry);
    }

    public function testListQueryBuilderWithoutFiltersOrdersByNewestFirst(): void
    {
        $qb = $this->fluentQueryBuilderMock();
        $qb->expects(self::once())->method('orderBy')->with('s.id', 'DESC')->willReturnSelf();
        $qb->expects(self::never())->method('andWhere');
        $qb->expects(self::never())->method('setParameter');
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $result = $this->repository->listQueryBuilder(new ShopListQuery());

        self::assertSame($qb, $result);
    }

    public function testListQueryBuilderWithNameFilterAppliesCaseInsensitiveLike(): void
    {
        $query = new ShopListQuery();
        $query->setName('  Paris  ');

        $qb = $this->fluentQueryBuilderMock();
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('LOWER(s.name) LIKE LOWER(:name)')
            ->willReturnSelf()
        ;
        $qb->expects(self::once())
            ->method('setParameter')
            ->with('name', '%Paris%')
            ->willReturnSelf()
        ;
        $qb->expects(self::once())->method('orderBy')->with('s.id', 'DESC')->willReturnSelf();
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $result = $this->repository->listQueryBuilder($query);

        self::assertSame($qb, $result);
    }

    public function testListQueryBuilderWithDistanceFilterAddsDistanceSelectAndOrdersByIt(): void
    {
        $query = new ShopListQuery();
        $query->latitude = 48.8566;
        $query->longitude = 2.3522;
        $query->radius = 10_000;

        $qb = $this->fluentQueryBuilderMock();
        $qb->expects(self::exactly(3))->method('andWhere')->willReturnSelf();
        $qb->expects(self::exactly(3))
            ->method('setParameter')
            ->willReturnSelf()
        ;
        $qb->expects(self::once())
            ->method('addSelect')
            ->with(self::stringContains('as distance'))
            ->willReturnSelf()
        ;
        $qb->expects(self::once())
            ->method('orderBy')
            ->with('distance', 'ASC')
            ->willReturnSelf()
        ;
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $result = $this->repository->listQueryBuilder($query);

        self::assertSame($qb, $result);
    }

    /**
     * @return QueryBuilder&MockObject
     */
    private function fluentQueryBuilderMock(): QueryBuilder
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();

        return $qb;
    }
}
