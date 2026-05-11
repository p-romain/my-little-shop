<?php

declare(strict_types=1);

namespace App\Tests;

final class ProductControllerTest extends ApiTestCase
{
    // Canonical shapes — the single source of truth for what the API exposes.
    private const ENVELOPE_KEYS = ['items', 'total', 'pages'];
    private const PRODUCT_KEYS = ['id', 'name', 'picture', 'stocks'];
    private const STOCK_KEYS = ['shop', 'quantity'];
    private const STOCK_SHOP_KEYS = ['id', 'name', 'address'];

    // Fixture totals (see fixtures/products.yaml, fixtures/stocks.yaml).
    private const FIXTURE_PRODUCT_COUNT = 100;
    private const FIXTURE_PAGE_SIZE = 30;
    // shop_paris fixture stocks reference products 1..10.
    private const FIXTURE_PARIS_PRODUCT_COUNT = 10;

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/products');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListShape(): void
    {
        $token = $this->getToken('admin@example.com');

        $this->client->request('GET', '/api/products', [], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertShape(self::ENVELOPE_KEYS, $data, 'envelope');
        $this->assertShape(self::PRODUCT_KEYS, $data['items'][0], 'product item');
        $this->assertShape(self::STOCK_KEYS, $data['items'][0]['stocks'][0], 'stock');
        $this->assertShape(self::STOCK_SHOP_KEYS, $data['items'][0]['stocks'][0]['shop'], 'stock shop');
    }

    public function testListReturnsProductsWithStocks(): void
    {
        $token = $this->getToken('admin@example.com');

        $this->client->request('GET', '/api/products', [], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(self::FIXTURE_PAGE_SIZE, $data['items']);
        self::assertSame(self::FIXTURE_PRODUCT_COUNT, $data['total']);
        self::assertSame(4, $data['pages']);
        self::assertNotEmpty($data['items'][0]['stocks']);
        self::assertGreaterThan(0, $data['items'][0]['stocks'][0]['quantity']);
    }

    public function testListFilterByShop(): void
    {
        $token = $this->getToken('admin@example.com');
        $shop = $this->getShop('Paris Champs-Elysees');

        $this->client->request('GET', '/api/products', ['shops' => [$shop->getId()]], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(self::FIXTURE_PARIS_PRODUCT_COUNT, $data['items']);
        self::assertSame(self::FIXTURE_PARIS_PRODUCT_COUNT, $data['total']);
        foreach ($data['items'] as $item) {
            self::assertSame('Paris Champs-Elysees', $item['stocks'][0]['shop']['name']);
        }
    }

    /**
     * @dataProvider classicSqlInjectionPayloads
     */
    public function testListShopFilterRejectsSqlInjectionPayloads(string $payload): void
    {
        $token = $this->getToken('admin@example.com');

        $this->client->request('GET', '/api/products', ['shops' => [$payload]], [], $this->authServer($token));

        self::assertResponseStatusCodeSame(400);
    }

    public function testStocksWithZeroQuantityAreExcluded(): void
    {
        $token = $this->getToken('admin@example.com');

        // Pick the lowest-id fixture product and zero out all of its stocks.
        // DAMA wraps the test in a transaction so this rolls back after the run.
        $product = $this->entityManager->getRepository(\App\Entity\Product::class)
            ->createQueryBuilder('p')
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult();
        foreach ($product->getStocks() as $stock) {
            $stock->setQuantity(0);
        }
        $this->entityManager->flush();

        $this->client->request('GET', '/api/products', [], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        $modified = null;
        foreach ($data['items'] as $item) {
            if ($item['id'] === $product->getId()) {
                $modified = $item;
                break;
            }
        }
        self::assertNotNull($modified, 'Product with zeroed stocks should still appear in the listing.');
        self::assertCount(0, $modified['stocks']);
    }

    public function testListPagination(): void
    {
        $token = $this->getToken('admin@example.com');

        $this->client->request('GET', '/api/products', ['page' => 1], [], $this->authServer($token));
        $page1 = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(self::FIXTURE_PAGE_SIZE, $page1['items']);
        self::assertSame(self::FIXTURE_PRODUCT_COUNT, $page1['total']);
        self::assertSame(4, $page1['pages']);

        $this->client->request('GET', '/api/products', ['page' => 4], [], $this->authServer($token));
        $page4 = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(self::FIXTURE_PRODUCT_COUNT - 3 * self::FIXTURE_PAGE_SIZE, $page4['items']);
    }
}
