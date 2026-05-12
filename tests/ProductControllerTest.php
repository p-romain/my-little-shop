<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Product;

final class ProductControllerTest extends ApiTestCase
{
    // Canonical shapes — the single source of truth for what the API exposes.
    private const ENVELOPE_KEYS = ['items', 'total', 'pages'];
    private const PRODUCT_KEYS = ['id', 'name', 'picture', 'stocks'];
    private const STOCK_KEYS = ['shop', 'quantity'];
    private const STOCK_SHOP_KEYS = ['id', 'name', 'address'];

    // Fixture totals (see fixtures/products.yaml, fixtures/stocks.yaml).
    // 100 generated products + 1 fixed product_zero_stock = 101.
    private const FIXTURE_PRODUCT_COUNT = 101;
    private const FIXTURE_PAGE_SIZE = 30;
    private const FIXTURE_PAGE_COUNT = 4;
    private const FIXTURE_LAST_PAGE_COUNT = self::FIXTURE_PRODUCT_COUNT - (self::FIXTURE_PAGE_COUNT - 1) * self::FIXTURE_PAGE_SIZE;
    // shop_paris fixture stocks reference products 1..10 (product_zero_stock has a zero-quantity stock there, excluded).
    private const FIXTURE_PARIS_PRODUCT_COUNT = 10;

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/products');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListShape(): void
    {
        $token = $this->getToken();

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
        $token = $this->getToken();

        $this->client->request('GET', '/api/products', [], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(self::FIXTURE_PAGE_SIZE, $data['items']);
        self::assertSame(self::FIXTURE_PRODUCT_COUNT, $data['total']);
        self::assertSame(self::FIXTURE_PAGE_COUNT, $data['pages']);
        self::assertNotEmpty($data['items'][0]['stocks']);
        self::assertGreaterThan(0, $data['items'][0]['stocks'][0]['quantity']);
    }

    public function testListFilterByShop(): void
    {
        $token = $this->getToken();
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
        $token = $this->getToken();

        $this->client->request('GET', '/api/products', ['shops' => [$payload]], [], $this->authServer($token));

        self::assertResponseStatusCodeSame(400);
    }

    public function testStocksWithZeroQuantityAreExcluded(): void
    {
        $token = $this->getToken();
        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['name' => 'Zero Stock Product']);
        self::assertNotNull($product, 'Fixture product "Zero Stock Product" should be loaded.');

        // product_zero_stock has the highest id (added last in fixtures) → last page in id-ASC order.
        $this->client->request('GET', '/api/products', ['page' => self::FIXTURE_PAGE_COUNT], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        $zeroStockItem = null;
        foreach ($data['items'] as $item) {
            if ($item['id'] === $product->getId()) {
                $zeroStockItem = $item;
                break;
            }
        }
        self::assertNotNull($zeroStockItem, 'Product with zero-quantity stocks should still appear in the listing.');
        self::assertSame([], $zeroStockItem['stocks']);
    }

    public function testListPagination(): void
    {
        $token = $this->getToken();

        $this->client->request('GET', '/api/products', ['page' => 1], [], $this->authServer($token));
        $page1 = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(self::FIXTURE_PAGE_SIZE, $page1['items']);
        self::assertSame(self::FIXTURE_PRODUCT_COUNT, $page1['total']);
        self::assertSame(self::FIXTURE_PAGE_COUNT, $page1['pages']);

        $this->client->request('GET', '/api/products', ['page' => self::FIXTURE_PAGE_COUNT], [], $this->authServer($token));
        $lastPage = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(self::FIXTURE_LAST_PAGE_COUNT, $lastPage['items']);
    }
}
