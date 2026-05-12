<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ManagerExists extends Constraint
{
    public string $message = 'Manager not found.';
}
