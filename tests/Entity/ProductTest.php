<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Product;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testGetIdReturnsNullBeforePersistence(): void
    {
        $product = new Product();

        self::assertNull($product->getId());
    }

    public function testGetNameReturnsNullByDefault(): void
    {
        $product = new Product();

        self::assertNull($product->getName());
    }

    public function testSetNameStoresValueAndReturnsSameInstance(): void
    {
        $product = new Product();

        $result = $product->setName('Linen Shirt Ivory');

        self::assertSame($product, $result);
        self::assertSame('Linen Shirt Ivory', $product->getName());
    }

    public function testGetPictureReturnsNullByDefault(): void
    {
        $product = new Product();

        self::assertNull($product->getPicture());
    }

    public function testSetPictureStoresValueAndReturnsSameInstance(): void
    {
        $product = new Product();

        $result = $product->setPicture('https://example.com/p.jpg');

        self::assertSame($product, $result);
        self::assertSame('https://example.com/p.jpg', $product->getPicture());
    }

    public function testStocksIsAnEmptyArrayByDefault(): void
    {
        $product = new Product();

        self::assertSame([], $product->stocks);
    }
}
