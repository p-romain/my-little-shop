<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\ShopInput;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ShopInputTest extends KernelTestCase
{
    public function testValidInputPassesValidation(): void
    {
        $input = new ShopInput();
        $input->name = 'Paris';
        $input->address = '18 Avenue des Champs-Elysees';
        $input->latitude = 48.8708;
        $input->longitude = 2.3059;
        $input->managerId = $this->managerId();

        self::assertSame([], $this->violationMessages($input));
    }

    /**
     * @dataProvider invalidInputs
     */
    public function testInvalidInputReturnsExpectedMessage(ShopInput $input, string $expectedMessage): void
    {
        self::assertContains($expectedMessage, $this->violationMessages($input));
    }

    /**
     * @return iterable<string, array{ShopInput, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'blank name' => [self::input(name: ''), 'Name is required.'];
        yield 'missing address' => [self::input(address: null), 'Address is required.'];
        yield 'missing latitude' => [self::input(latitude: null), 'Latitude is required.'];
        yield 'missing longitude' => [self::input(longitude: null), 'Longitude is required.'];
        yield 'missing manager' => [self::input(managerId: null), 'Manager is required.'];
        yield 'zero manager' => [self::input(managerId: 0), 'Manager is required.'];
        yield 'unknown manager' => [self::input(managerId: 99999), 'Manager not found.'];
    }

    private static function input(
        ?string $name = 'Paris',
        ?string $address = '18 Avenue des Champs-Elysees',
        ?float $latitude = 48.8708,
        ?float $longitude = 2.3059,
        ?int $managerId = 1,
    ): ShopInput {
        $input = new ShopInput();
        $input->name = $name;
        $input->address = $address;
        $input->latitude = $latitude;
        $input->longitude = $longitude;
        $input->managerId = $managerId;

        return $input;
    }

    /**
     * @return string[]
     */
    private function violationMessages(ShopInput $input): array
    {
        $validator = static::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        return array_map(
            static fn ($violation): string => (string) $violation->getMessage(),
            iterator_to_array($validator->validate($input)),
        );
    }

    private function managerId(): int
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $manager = $entityManager->getRepository(User::class)->findOneBy(['email' => 'manager1@example.com']);
        self::assertInstanceOf(User::class, $manager);
        self::assertIsInt($manager->getId());

        return $manager->getId();
    }
}
