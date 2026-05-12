<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Product;
use App\Entity\Stock;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StockRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private StockRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(Stock::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);

        $this->repository = new StockRepository($registry);
    }

    public function testFindByProductIdsReturnsEmptyArrayForEmptyProductListWithoutTouchingTheEntityManager(): void
    {
        $this->entityManager->expects(self::never())->method('createQueryBuilder');

        self::assertSame([], $this->repository->findByProductIds([]));
    }

    public function testFindByProductIdsGroupsPositiveStocksByProductId(): void
    {
        $firstProduct = $this->productWithId(1);
        $secondProduct = $this->productWithId(2);
        $stocks = [
            $this->stockFor($firstProduct),
            $this->stockFor($firstProduct),
            $this->stockFor($secondProduct),
        ];

        $qb = $this->fluentQueryBuilderMock();
        $qb->expects(self::once())->method('where')->with('s.product IN (:ids)')->willReturnSelf();
        $qb
            ->expects(self::once())
            ->method('andWhere')
            ->with('s.quantity > 0')
            ->willReturnSelf()
        ;
        $qb
            ->expects(self::once())
            ->method('setParameter')
            ->with('ids', [1, 2])
            ->willReturnSelf()
        ;
        $qb->method('getQuery')->willReturn($this->queryReturning($stocks));
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $grouped = $this->repository->findByProductIds([1, 2]);

        self::assertArrayHasKey(1, $grouped);
        self::assertCount(2, $grouped[1]);
        self::assertArrayHasKey(2, $grouped);
        self::assertCount(1, $grouped[2]);
    }

    public function testFindByProductIdsAppliesShopFilterWhenShopIdsProvided(): void
    {
        $qb = $this->fluentQueryBuilderMock();
        $qb->method('where')->willReturnSelf();
        $qb
            ->expects(self::exactly(2))
            ->method('andWhere')
            ->willReturnSelf()
        ;
        $qb
            ->expects(self::exactly(2))
            ->method('setParameter')
            ->willReturnSelf()
        ;
        $qb->method('getQuery')->willReturn($this->queryReturning([]));
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $this->repository->findByProductIds([1], [10]);
    }

    /**
     * @return QueryBuilder&MockObject
     */
    private function fluentQueryBuilderMock(): QueryBuilder
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('join')->willReturnSelf();

        return $qb;
    }

    /**
     * @param Stock[] $stocks
     */
    private function queryReturning(array $stocks): Query
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock()
        ;
        $query->method('getResult')->willReturn($stocks);

        return $query;
    }

    private function productWithId(int $id): Product
    {
        $product = new Product();
        $reflection = new \ReflectionProperty(Product::class, 'id');
        $reflection->setValue($product, $id);

        return $product;
    }

    private function stockFor(Product $product): Stock
    {
        return (new Stock())->setProduct($product);
    }
}
