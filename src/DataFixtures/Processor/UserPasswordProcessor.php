<?php

namespace App\DataFixtures\Processor;

use App\Entity\User;
use Fidry\AliceDataFixtures\ProcessorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function preProcess(string $id, object $object): void
    {
        if (!$object instanceof User || null === $object->getPlainPassword()) {
            return;
        }

        $object->setPassword($this->passwordHasher->hashPassword($object, $object->getPlainPassword()));
        $object->setPlainPassword(null);
    }

    public function postProcess(string $id, object $object): void
    {
    }
}
