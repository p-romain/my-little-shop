<?php

namespace App\Validator;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ManagerExistsValidator extends ConstraintValidator
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ManagerExists) {
            throw new UnexpectedTypeException($constraint, ManagerExists::class);
        }

        if (null === $value || !is_int($value) || $value <= 0) {
            return;
        }

        if ($this->users->find($value) instanceof User) {
            return;
        }

        $this->context
            ->buildViolation($constraint->message)
            ->addViolation()
        ;
    }
}
