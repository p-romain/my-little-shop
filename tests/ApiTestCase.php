<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Shop;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->clear();
    }

    protected function getUser(string $email): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user, "Fixture user with email '{$email}' was not loaded.");

        return $user;
    }

    protected function getShop(string $name): Shop
    {
        $shop = $this->entityManager->getRepository(Shop::class)->findOneBy(['name' => $name]);
        self::assertNotNull($shop, "Fixture shop with name '{$name}' was not loaded.");

        return $shop;
    }

    protected function getToken(string $email = 'admin@example.com'): string
    {
        $this->client->jsonRequest('POST', '/api/login', ['email' => $email, 'password' => 'password']);
        /** @var array{token: string} $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        return $data['token'];
    }

    /** @return array<string, string> */
    protected function authServer(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
    }

    /**
     * Asserts that $actual contains exactly the listed keys — no more, no less.
     * This catches both missing fields and unexpected extras (e.g. leaked password hashes).
     *
     * @param string[] $expectedKeys
     */
    protected function assertShape(array $expectedKeys, array $actual, string $context = ''): void
    {
        $label = '' !== $context ? "[$context] " : '';

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $actual, $label."Missing expected key '$key'.");
        }

        self::assertCount(
            count($expectedKeys),
            $actual,
            $label.'Expected exactly '.count($expectedKeys).' key(s) ['.implode(', ', $expectedKeys).']'
                .', got '.count($actual).' ['.implode(', ', array_keys($actual)).'].',
        );
    }

    /**
     * Representative SQL injection payloads for filters and JSON payload fields.
     *
     * @return iterable<string, array{string}>
     */
    public static function classicSqlInjectionPayloads(): iterable
    {
        yield 'single quote tautology' => ["' OR '1'='1"];
        yield 'numeric tautology' => ['1 OR 1=1'];
        yield 'comment truncation' => ["admin'--"];
        yield 'hash comment truncation' => ["' OR 1=1#"];
        yield 'stacked drop' => ["'; DROP TABLE shop; --"];
        yield 'stacked delete' => ['0; DELETE FROM users; --'];
        yield 'union select' => ["' UNION SELECT NULL--"];
        yield 'parenthesized tautology' => ["') OR ('1'='1"];
        yield 'double quote tautology' => ['" OR "1"="1'];
        yield 'time delay' => ["'; SELECT pg_sleep(1); --"];
    }
}
