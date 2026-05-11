<?php

declare(strict_types=1);

namespace App\Tests;

final class UserControllerTest extends ApiTestCase
{
    // Canonical shapes — the single source of truth for what the API exposes.
    private const ENVELOPE_KEYS = ['items', 'total', 'pages'];
    private const USER_KEYS = ['id', 'email'];

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/users');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListShape(): void
    {
        $this->createUser();
        $token = $this->getToken();

        $this->client->request('GET', '/api/users', [], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertShape(self::ENVELOPE_KEYS, $data, 'envelope');
        $this->assertShape(self::USER_KEYS, $data['items'][0], 'user item');
    }

    public function testListReturnsUsers(): void
    {
        $user = $this->createUser();
        $token = $this->getToken();

        $this->client->request('GET', '/api/users', [], [], $this->authServer($token));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $data['items']);
        self::assertSame($user->getEmail(), $data['items'][0]['email']);
        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['pages']);
    }

    public function testListPagination(): void
    {
        for ($i = 1; $i <= 31; ++$i) {
            $this->createUser("user{$i}@example.com");
        }
        $token = $this->getToken('user1@example.com');

        $this->client->request('GET', '/api/users', ['page' => 1], [], $this->authServer($token));
        $page1 = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(30, $page1['items']);
        self::assertSame(31, $page1['total']);
        self::assertSame(2, $page1['pages']);

        $this->client->request('GET', '/api/users', ['page' => 2], [], $this->authServer($token));
        $page2 = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $page2['items']);
    }
}
