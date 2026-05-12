<?php

declare(strict_types=1);

namespace App\Tests;

final class LoginTest extends ApiTestCase
{
    public function testSuccessfulLogin(): void
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $data);
        self::assertIsString($data['token']);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginWithUnknownUserReturns401(): void
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        self::assertResponseStatusCodeSame(401);
    }
}
