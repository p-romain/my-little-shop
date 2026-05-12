<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Product;
use App\Entity\Shop;
use App\Entity\Stock;
use PHPUnit\Framework\TestCase;

final class StockTest extends TestCase
{
    public function testGetIdReturnsNullBeforePersistence(): void
    {
        $stock = new Stock();

        self::assertNull($stock->getId());
    }

    public function testGetProductReturnsNullByDefault(): void
    {
        $stock = new Stock();

        self::assertNull($stock->getProduct());
    }

    public function testSetProductStoresValueAndReturnsSameInstance(): void
    {
        $stock = new Stock();
        $product = new Product();

        $result = $stock->setProduct($product);

        self::assertSame($stock, $result);
        self::assertSame($product, $stock->getProduct());
    }

    public function testGetShopReturnsNullByDefault(): void
    {
        $stock = new Stock();

        self::assertNull($stock->getShop());
    }

    public function testSetShopStoresValueAndReturnsSameInstance(): void
    {
        $stock = new Stock();
        $shop = new Shop();

        $result = $stock->setShop($shop);

        self::assertSame($stock, $result);
        self::assertSame($shop, $stock->getShop());
    }

    public function testGetQuantityReturnsZeroByDefault(): void
    {
        $stock = new Stock();

        self::assertSame(0, $stock->getQuantity());
    }

    public function testSetQuantityStoresValueAndReturnsSameInstance(): void
    {
        $stock = new Stock();

        $result = $stock->setQuantity(42);

        self::assertSame($stock, $result);
        self::assertSame(42, $stock->getQuantity());
    }
}
