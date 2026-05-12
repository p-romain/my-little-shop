<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\ShopListQuery;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ShopListQueryTest extends KernelTestCase
{
    public function testSetNameTrimsValue(): void
    {
        $query = new ShopListQuery();

        $query->setName('  Paris  ');

        self::assertSame('Paris', $query->name);
    }

    public function testSetNameConvertsBlankValueToNull(): void
    {
        $query = new ShopListQuery();

        $query->setName('   ');

        self::assertNull($query->name);
    }

    public function testSetNameAcceptsNull(): void
    {
        $query = new ShopListQuery();
        $query->name = 'Paris';

        $query->setName(null);

        self::assertNull($query->name);
    }

    public function testCompleteLocationFilterPassesValidation(): void
    {
        $query = new ShopListQuery();
        $query->latitude = 48.8566;
        $query->longitude = 2.3522;
        $query->radius = 10_000;

        self::assertSame([], $this->violationMessages($query));
    }

    /**
     * @dataProvider partialLocationQueries
     */
    public function testPartialLocationFilterFailsValidation(ShopListQuery $query): void
    {
        self::assertContains(
            'latitude, longitude and radius must all be provided together.',
            $this->violationMessages($query),
        );
    }

    public function testNonPositiveRadiusFailsValidation(): void
    {
        $query = new ShopListQuery();
        $query->latitude = 48.8566;
        $query->longitude = 2.3522;
        $query->radius = 0;

        self::assertSame(
            ['latitude and longitude must be numbers, radius must be a positive integer.'],
            $this->violationMessages($query),
        );
    }

    /**
     * @return iterable<string, array{ShopListQuery}>
     */
    public static function partialLocationQueries(): iterable
    {
        yield 'latitude only' => [self::query(latitude: 48.8566)];
        yield 'longitude only' => [self::query(longitude: 2.3522)];
        yield 'radius only' => [self::query(radius: 10_000)];
        yield 'latitude and longitude' => [self::query(latitude: 48.8566, longitude: 2.3522)];
    }

    private static function query(?float $latitude = null, ?float $longitude = null, ?int $radius = null): ShopListQuery
    {
        $query = new ShopListQuery();
        $query->latitude = $latitude;
        $query->longitude = $longitude;
        $query->radius = $radius;

        return $query;
    }

    /**
     * @return string[]
     */
    private function violationMessages(ShopListQuery $query): array
    {
        $validator = static::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        return array_map(
            static fn ($violation): string => (string) $violation->getMessage(),
            iterator_to_array($validator->validate($query)),
        );
    }
}
