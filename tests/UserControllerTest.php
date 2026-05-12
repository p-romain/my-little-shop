<?php

declare(strict_types=1);

namespace App\Tests;

final class UserControllerTest extends ApiTestCase
{
    // Canonical shapes — the single source of truth for what the API exposes.
    private const ENVELOPE_KEYS = ['items', 'total', 'pages'];
    private const USER_KEYS = ['id', 'email'];

    // Fixture totals (see fixtures/users.yaml): admin + manager1 + manager2 + user_{1..32}.
    private const FIXTURE_USER_COUNT = 35;
    private const FIXTURE_PAGE_SIZE = 30;

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/users');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListShape(): void
    {
        $token = $this->getToken();

        $this->client->request('GET', '/api/users', [], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertShape(self::ENVELOPE_KEYS, $data, 'envelope');
        $this->assertShape(self::USER_KEYS, $data['items'][0], 'user item');
    }

    public function testListReturnsUsers(): void
    {
        $token = $this->getToken();

        $this->client->request('GET', '/api/users', [], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertContains('admin@example.com', array_column($data['items'], 'email'));
        self::assertSame(self::FIXTURE_USER_COUNT, $data['total']);
        self::assertSame(2, $data['pages']);
    }

    public function testListPagination(): void
    {
        $token = $this->getToken();

        $this->client->request('GET', '/api/users', ['page' => 1], [], $this->authServer($token));
        $page1 = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(self::FIXTURE_PAGE_SIZE, $page1['items']);
        self::assertSame(self::FIXTURE_USER_COUNT, $page1['total']);
        self::assertSame(2, $page1['pages']);

        $this->client->request('GET', '/api/users', ['page' => 2], [], $this->authServer($token));
        $page2 = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(self::FIXTURE_USER_COUNT - self::FIXTURE_PAGE_SIZE, $page2['items']);
    }
}
