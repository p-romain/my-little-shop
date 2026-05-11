<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures\Processor;

use App\DataFixtures\Processor\UserPasswordProcessor;
use App\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserPasswordProcessorTest extends TestCase
{
    private UserPasswordHasherInterface&MockObject $hasher;
    private UserPasswordProcessor $processor;

    protected function setUp(): void
    {
        $this->hasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->processor = new UserPasswordProcessor($this->hasher);
    }

    public function testPreProcessIgnoresNonUserObjects(): void
    {
        $this->hasher->expects(self::never())->method('hashPassword');

        $this->processor->preProcess('fixture_id', new \stdClass());
    }

    public function testPreProcessIgnoresUserWithNullPlainPassword(): void
    {
        $this->hasher->expects(self::never())->method('hashPassword');

        $user = (new User())->setEmail('test@example.com');
        // plainPassword is null by default

        $this->processor->preProcess('fixture_id', $user);
    }

    public function testPreProcessHashesPasswordAndStoresIt(): void
    {
        $user = (new User())->setEmail('test@example.com')->setPlainPassword('secret');

        $this->hasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with($user, 'secret')
            ->willReturn('$hashed$value');

        $this->processor->preProcess('fixture_id', $user);

        self::assertSame('$hashed$value', $user->getPassword());
    }

    public function testPreProcessClearsPlainPasswordAfterHashing(): void
    {
        $user = (new User())->setEmail('test@example.com')->setPlainPassword('secret');

        $this->hasher->method('hashPassword')->willReturn('$hashed$value');

        $this->processor->preProcess('fixture_id', $user);

        self::assertNull($user->getPlainPassword());
    }

    public function testPostProcessDoesNothing(): void
    {
        $this->hasher->expects(self::never())->method('hashPassword');

        // Must not throw, must not interact with the hasher
        $this->processor->postProcess('fixture_id', new User());
    }
}
