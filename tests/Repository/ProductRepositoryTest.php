<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProductRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ProductRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(Product::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);

        $this->repository = new ProductRepository($registry);
    }

    public function testListQueryBuilderWithoutShopFilterOnlyOrdersById(): void
    {
        $qb = $this->fluentQueryBuilderMock();
        $qb->expects(self::once())->method('orderBy')->with('p.id', 'ASC')->willReturnSelf();
        $qb->expects(self::never())->method('where');
        $qb->expects(self::never())->method('setParameter');
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $result = $this->repository->listQueryBuilder([]);

        self::assertSame($qb, $result);
    }

    public function testListQueryBuilderWithShopFilterAddsExistsClauseAndShopIdsParameter(): void
    {
        $mainQb = $this->fluentQueryBuilderMock();
        $mainQb->method('orderBy')->willReturnSelf();
        $mainQb
            ->expects(self::once())
            ->method('where')
            ->with(self::stringContains('EXISTS ('))
            ->willReturnSelf()
        ;
        $mainQb
            ->expects(self::once())
            ->method('setParameter')
            ->with('shopIds', [42, 43])
            ->willReturnSelf()
        ;

        $subQb = $this->fluentQueryBuilderMock();
        $subQb->method('getDQL')->willReturn('SELECT 1 FROM stock');

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($mainQb, $subQb)
        ;

        $result = $this->repository->listQueryBuilder([42, 43]);

        self::assertSame($mainQb, $result);
    }

    /**
     * @return QueryBuilder&MockObject
     */
    private function fluentQueryBuilderMock(): QueryBuilder
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();

        return $qb;
    }
}
