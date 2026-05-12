<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\ProductListQuery;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ProductListQueryTest extends KernelTestCase
{
    public function testValidShopIdsPassValidation(): void
    {
        $query = new ProductListQuery();
        $query->shops = ['1', '42'];

        self::assertSame([], $this->violationMessages($query));
    }

    /**
     * @dataProvider invalidShopIds
     *
     * @param string[] $shopIds
     */
    public function testInvalidShopIdsFailValidation(array $shopIds): void
    {
        $query = new ProductListQuery();
        $query->shops = $shopIds;

        self::assertSame(
            ['shops must contain positive integer ids only.'],
            $this->violationMessages($query),
        );
    }

    /**
     * @return iterable<string, array{string[]}>
     */
    public static function invalidShopIds(): iterable
    {
        yield 'zero' => [['0']];
        yield 'negative' => [['-1']];
        yield 'decimal' => [['1.5']];
        yield 'text' => [['abc']];
    }

    /**
     * @return string[]
     */
    private function violationMessages(ProductListQuery $query): array
    {
        $validator = static::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        return array_map(
            static fn ($violation): string => (string) $violation->getMessage(),
            iterator_to_array($validator->validate($query)),
        );
    }
}
