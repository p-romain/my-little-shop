<?php

declare(strict_types=1);

namespace App\Tests;

final class ShopControllerTest extends ApiTestCase
{
    // Canonical shapes — the single source of truth for what the API exposes.
    private const ENVELOPE_KEYS = ['items', 'total', 'pages'];
    private const SHOP_LIST_KEYS = ['id', 'name', 'latitude', 'longitude', 'address', 'manager', 'distance'];
    private const SHOP_DETAIL_KEYS = ['id', 'name', 'latitude', 'longitude', 'address', 'manager'];
    private const MANAGER_KEYS = ['id', 'email'];

    // Fixture totals (see fixtures/shops.yaml, fixtures/users.yaml).
    private const FIXTURE_SHOP_COUNT = 30;
    private const FIXTURE_USER_COUNT = 3;

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/shops');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListShape(): void
    {
        $token = $this->getToken('admin@example.com');

        $this->client->request('GET', '/api/shops', [], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertShape(self::ENVELOPE_KEYS, $data, 'envelope');
        $this->assertShape(self::SHOP_LIST_KEYS, $data['items'][0], 'shop item');
        $this->assertShape(self::MANAGER_KEYS, $data['items'][0]['manager'], 'manager');
    }

    public function testGetShape(): void
    {
        $token = $this->getToken('admin@example.com');
        $shop = $this->getShop('Paris Champs-Elysees');

        $this->client->request('GET', '/api/shops/'.$shop->getId(), [], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertShape(self::SHOP_DETAIL_KEYS, $data, 'shop');
        $this->assertShape(self::MANAGER_KEYS, $data['manager'], 'manager');
    }

    public function testListReturnsShops(): void
    {
        $token = $this->getToken('admin@example.com');

        $this->client->request('GET', '/api/shops', [], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(self::FIXTURE_SHOP_COUNT, $data['items']);
        self::assertNull($data['items'][0]['distance']);
        self::assertSame(self::FIXTURE_SHOP_COUNT, $data['total']);
        self::assertSame(1, $data['pages']);
    }

    public function testListFilterByNameCaseInsensitive(): void
    {
        $token = $this->getToken('admin@example.com');

        // 'paris' matches only 'Paris Champs-Elysees' in fixtures.
        $this->client->request('GET', '/api/shops', ['name' => 'paris'], [], $this->authServer($token));

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $data['items']);
        self::assertSame('Paris Champs-Elysees', $data['items'][0]['name']);
    }

    /**
     * @dataProvider classicSqlInjectionPayloads
     */
    public function testListNameFilterWithSqlInjectionPayloadsDoesNotBroadenResults(string $payload): void
    {
        $token = $this->getToken('admin@example.com');

        $this->client->request('GET', '/api/shops', ['name' => $payload], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(0, $data['total']);
        self::assertCount(0, $data['items']);
    }

    /**
     * @dataProvider classicSqlInjectionPayloads
     */
    public function testListLocationFiltersRejectSqlInjectionPayloads(string $payload): void
    {
        $token = $this->getToken('admin@example.com');

        $this->client->request('GET', '/api/shops', [
            'latitude' => $payload,
            'longitude' => $payload,
            'radius' => $payload,
        ], [], $this->authServer($token));

        self::assertResponseStatusCodeSame(400);
    }

    public function testListFilterByLocationReturnsNearbyShopsWithDistance(): void
    {
        $token = $this->getToken('admin@example.com');

        // Paris Champs-Elysees fixture is at (48.8708, 2.3059); ~1.6 km from (48.8566, 2.3522).
        // No other fixture shop sits within 10 km of this point.
        $this->client->request('GET', '/api/shops', [
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'radius' => 10000,
        ], [], $this->authServer($token));

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $data['items']);
        self::assertSame('Paris Champs-Elysees', $data['items'][0]['name']);
        self::assertIsInt($data['items'][0]['distance']);
        self::assertLessThan(10000, $data['items'][0]['distance']);
    }

    public function testListWithPartialLocationParamsReturnsBadRequest(): void
    {
        $token = $this->getToken('admin@example.com');

        $this->client->request('GET', '/api/shops', [
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            // radius missing
        ], [], $this->authServer($token));

        self::assertResponseStatusCodeSame(400);
    }

    public function testGetReturnsShop(): void
    {
        $token = $this->getToken('admin@example.com');
        $shop = $this->getShop('Paris Champs-Elysees');

        $this->client->request('GET', '/api/shops/'.$shop->getId(), [], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame($shop->getId(), $data['id']);
        self::assertSame($shop->getName(), $data['name']);
    }

    public function testGetReturnsNotFoundForUnknownShop(): void
    {
        $token = $this->getToken('admin@example.com');

        $this->client->request('GET', '/api/shops/99999', [], [], $this->authServer($token));

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateShop(): void
    {
        $token = $this->getToken('admin@example.com');
        $manager = $this->getUser('manager1@example.com');

        $this->client->jsonRequest('POST', '/api/shops', [
            'name' => 'New Shop',
            'address' => '1 rue de Rivoli, Paris',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'managerId' => $manager->getId(),
        ], $this->authServer($token));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertShape(self::SHOP_DETAIL_KEYS, $data, 'created shop');
        self::assertSame('New Shop', $data['name']);
        self::assertSame($manager->getId(), $data['manager']['id']);
    }

    public function testCreateShopRequiresAuthentication(): void
    {
        $this->client->jsonRequest('POST', '/api/shops', ['name' => 'Shop']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateShopWithMissingNameReturnsBadRequest(): void
    {
        $token = $this->getToken('admin@example.com');
        $manager = $this->getUser('manager1@example.com');

        $this->client->jsonRequest('POST', '/api/shops', [
            'address' => '1 rue Test',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'managerId' => $manager->getId(),
        ], $this->authServer($token));

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateShopWithMissingAddressReturnsBadRequest(): void
    {
        $token = $this->getToken('admin@example.com');
        $manager = $this->getUser('manager1@example.com');

        $this->client->jsonRequest('POST', '/api/shops', [
            'name' => 'Shop',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'managerId' => $manager->getId(),
        ], $this->authServer($token));

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateShopWithInvalidManagerReturnsBadRequest(): void
    {
        $token = $this->getToken('admin@example.com');

        $this->client->jsonRequest('POST', '/api/shops', [
            'name' => 'Shop',
            'address' => '1 rue Test',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'managerId' => 99999,
        ], $this->authServer($token));

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @dataProvider classicSqlInjectionPayloads
     */
    public function testCreateShopWithSqlInjectionPayloadsStoresStringsLiterally(string $payload): void
    {
        $token = $this->getToken('admin@example.com');
        $manager = $this->getUser('manager1@example.com');

        $this->client->jsonRequest('POST', '/api/shops', [
            'name' => $payload,
            'address' => $payload,
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'managerId' => $manager->getId(),
        ], $this->authServer($token));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame($payload, $data['name']);
        self::assertSame($payload, $data['address']);
        self::assertSame(self::FIXTURE_SHOP_COUNT + 1, $this->entityManager->getRepository(\App\Entity\Shop::class)->count([]));
        self::assertSame(self::FIXTURE_USER_COUNT, $this->entityManager->getRepository(\App\Entity\User::class)->count([]));
    }

    /**
     * @dataProvider classicSqlInjectionPayloads
     */
    public function testCreateShopRejectsSqlInjectionPayloadsForManagerId(string $payload): void
    {
        $token = $this->getToken('admin@example.com');

        $this->client->jsonRequest('POST', '/api/shops', [
            'name' => 'Shop',
            'address' => '1 rue Test',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'managerId' => $payload,
        ], $this->authServer($token));

        self::assertResponseStatusCodeSame(400);
        self::assertSame(self::FIXTURE_SHOP_COUNT, $this->entityManager->getRepository(\App\Entity\Shop::class)->count([]));
    }

    public function testUpdateShop(): void
    {
        $token = $this->getToken('admin@example.com');
        $manager = $this->getUser('manager1@example.com');
        $shop = $this->getShop('Paris Champs-Elysees');

        $this->client->jsonRequest('PUT', '/api/shops/'.$shop->getId(), [
            'name' => 'New Name',
            'address' => (string) $shop->getAddress(),
            'latitude' => $shop->getLatitude(),
            'longitude' => $shop->getLongitude(),
            'managerId' => $manager->getId(),
        ], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertShape(self::SHOP_DETAIL_KEYS, $data, 'updated shop');
        self::assertSame('New Name', $data['name']);
    }

    public function testUpdateShopRequiresAuthentication(): void
    {
        $this->client->jsonRequest('PUT', '/api/shops/1', ['name' => 'Shop']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUpdateShopReturnsNotFoundForUnknownShop(): void
    {
        $token = $this->getToken('admin@example.com');
        $manager = $this->getUser('manager1@example.com');

        $this->client->jsonRequest('PUT', '/api/shops/99999', [
            'name' => 'Name',
            'address' => '1 rue Test',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'managerId' => $manager->getId(),
        ], $this->authServer($token));

        self::assertResponseStatusCodeSame(404);
    }

    public function testUpdateShopWithMissingLatitudeReturnsBadRequest(): void
    {
        $token = $this->getToken('admin@example.com');
        $manager = $this->getUser('manager1@example.com');
        $shop = $this->getShop('Paris Champs-Elysees');

        $this->client->jsonRequest('PUT', '/api/shops/'.$shop->getId(), [
            'name' => 'Shop',
            'address' => '1 rue Test',
            'managerId' => $manager->getId(),
            // latitude and longitude missing
        ], $this->authServer($token));

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @dataProvider classicSqlInjectionPayloads
     */
    public function testUpdateShopWithSqlInjectionPayloadsStoresStringsLiterally(string $payload): void
    {
        $token = $this->getToken('admin@example.com');
        $manager = $this->getUser('manager1@example.com');
        $shop = $this->getShop('Paris Champs-Elysees');

        $this->client->jsonRequest('PUT', '/api/shops/'.$shop->getId(), [
            'name' => $payload,
            'address' => $payload,
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'managerId' => $manager->getId(),
        ], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame($payload, $data['name']);
        self::assertSame($payload, $data['address']);
        self::assertSame(self::FIXTURE_SHOP_COUNT, $this->entityManager->getRepository(\App\Entity\Shop::class)->count([]));
        self::assertSame(self::FIXTURE_USER_COUNT, $this->entityManager->getRepository(\App\Entity\User::class)->count([]));
    }

    /**
     * @dataProvider classicSqlInjectionPayloads
     */
    public function testUpdateShopRejectsSqlInjectionPayloadsForManagerId(string $payload): void
    {
        $token = $this->getToken('admin@example.com');
        $shop = $this->getShop('Paris Champs-Elysees');
        $originalName = $shop->getName();

        $this->client->jsonRequest('PUT', '/api/shops/'.$shop->getId(), [
            'name' => 'Shop',
            'address' => '1 rue Test',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'managerId' => $payload,
        ], $this->authServer($token));

        self::assertResponseStatusCodeSame(400);
        $this->entityManager->clear();
        $storedShop = $this->entityManager->getRepository(\App\Entity\Shop::class)->find($shop->getId());
        self::assertSame($originalName, $storedShop?->getName());
    }
}
