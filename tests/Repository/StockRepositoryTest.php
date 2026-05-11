<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Product;
use App\Entity\Shop;
use App\Entity\Stock;
use App\Repository\StockRepository;
use App\Tests\ApiTestCase;

final class StockRepositoryTest extends ApiTestCase
{
    public function testFindByProductIdsReturnsEmptyArrayForEmptyProductList(): void
    {
        self::assertSame([], $this->repository()->findByProductIds([]));
    }

    public function testFindByProductIdsGroupsPositiveStocksByProductId(): void
    {
        $products = $this->productsByFixtureNumber(4, 2);

        $stocksByProduct = $this->repository()->findByProductIds(array_map(
            static fn (Product $product): int => (int) $product->getId(),
            $products,
        ));

        self::assertArrayHasKey((int) $products[4]->getId(), $stocksByProduct);
        self::assertCount(2, $stocksByProduct[(int) $products[4]->getId()]);
        self::assertArrayHasKey((int) $products[2]->getId(), $stocksByProduct);
        self::assertCount(1, $stocksByProduct[(int) $products[2]->getId()]);
        self::assertContainsOnlyInstancesOf(Stock::class, $stocksByProduct[(int) $products[4]->getId()]);
    }

    public function testFindByProductIdsCanRestrictStocksToSpecificShops(): void
    {
        $product = $this->productByFixtureNumber(4);
        $shop = $this->getShop('Paris Champs-Elysees');

        $stocksByProduct = $this->repository()->findByProductIds([(int) $product->getId()], [(int) $shop->getId()]);

        self::assertArrayHasKey((int) $product->getId(), $stocksByProduct);
        self::assertCount(1, $stocksByProduct[(int) $product->getId()]);
        self::assertSame($shop->getId(), $stocksByProduct[(int) $product->getId()][0]->getShop()?->getId());
    }

    public function testFindByProductIdsExcludesZeroQuantityStocks(): void
    {
        $product = $this->productByFixtureNumber(4);
        $shop = $this->getShop('Paris Champs-Elysees');
        $stock = $this->entityManager->getRepository(Stock::class)->findOneBy([
            'product' => $product,
            'shop' => $shop,
        ]);
        self::assertNotNull($stock);
        $stock->setQuantity(0);
        $this->entityManager->flush();

        $stocksByProduct = $this->repository()->findByProductIds([(int) $product->getId()], [(int) $shop->getId()]);

        self::assertSame([], $stocksByProduct);
    }

    private function repository(): StockRepository
    {
        return $this->entityManager->getRepository(Stock::class);
    }

    /**
     * @return array<int, Product>
     */
    private function productsByFixtureNumber(int ...$numbers): array
    {
        $products = [];
        foreach ($numbers as $number) {
            $products[$number] = $this->productByFixtureNumber($number);
        }

        return $products;
    }

    private function productByFixtureNumber(int $number): Product
    {
        $product = $this->entityManager->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->where('p.name LIKE :suffix')
            ->setParameter('suffix', '% '.$number)
            ->getQuery()
            ->getSingleResult()
        ;
        self::assertInstanceOf(Product::class, $product);

        return $product;
    }
}
