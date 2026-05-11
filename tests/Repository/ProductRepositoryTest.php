<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Product;
use App\Entity\Shop;
use App\Repository\ProductRepository;
use App\Tests\ApiTestCase;

final class ProductRepositoryTest extends ApiTestCase
{
    public function testListQueryBuilderWithoutShopFilterReturnsEveryProductOrderedById(): void
    {
        $products = $this->repository()->listQueryBuilder([])
            ->setMaxResults(3)
            ->getQuery()
            ->getResult()
        ;

        self::assertCount(3, $products);
        self::assertContainsOnlyInstancesOf(Product::class, $products);
        self::assertLessThan($products[1]->getId(), $products[0]->getId());
        self::assertLessThan($products[2]->getId(), $products[1]->getId());
    }

    public function testListQueryBuilderWithShopFilterReturnsProductsInStockForThatShop(): void
    {
        $shop = $this->getShop('Paris Champs-Elysees');

        $products = $this->repository()->listQueryBuilder([(int) $shop->getId()])
            ->getQuery()
            ->getResult()
        ;

        self::assertCount(10, $products);
        self::assertSame(range(1, 10), array_map(
            static fn (Product $product): int => self::fixtureProductNumber($product),
            $products,
        ));
    }

    public function testListQueryBuilderWithShopFilterExcludesProductsWithOnlyZeroQuantityStock(): void
    {
        $shop = $this->getShop('Paris Champs-Elysees');
        $product = $this->repository()->findOneBy([], ['id' => 'ASC']);
        self::assertNotNull($product);

        $stock = $this->entityManager->getRepository(\App\Entity\Stock::class)->findOneBy([
            'product' => $product,
            'shop' => $shop,
        ]);
        self::assertNotNull($stock);
        $stock->setQuantity(0);
        $this->entityManager->flush();

        $products = $this->repository()->listQueryBuilder([(int) $shop->getId()])
            ->getQuery()
            ->getResult()
        ;

        self::assertNotContains($product, $products);
    }

    private function repository(): ProductRepository
    {
        return $this->entityManager->getRepository(Product::class);
    }

    private static function fixtureProductNumber(Product $product): int
    {
        self::assertMatchesRegularExpression('/\s(\d+)$/', (string) $product->getName());

        return (int) preg_replace('/^.*\s(\d+)$/', '$1', (string) $product->getName());
    }
}
