<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ShopListQuery
{
    public ?string $name = null;

    public function setName(?string $value): void
    {
        if (null === $value) {
            $this->name = null;

            return;
        }
        $trimmed = trim($value);
        $this->name = '' === $trimmed ? null : $trimmed;
    }

    public ?float $latitude = null;

    public ?float $longitude = null;

    #[Assert\Positive(message: 'Latitude and longitude must be numbers, radius must be a positive integer.')]
    public ?int $radius = null;

    #[Assert\Callback]
    public function validateLocation(ExecutionContextInterface $context): void
    {
        $provided = (int) (null !== $this->latitude)
            + (int) (null !== $this->longitude)
            + (int) (null !== $this->radius);

        if (0 === $provided || 3 === $provided) {
            return;
        }

        foreach (['latitude', 'longitude', 'radius'] as $property) {
            if (null !== $this->{$property}) {
                continue;
            }

            $context
                ->buildViolation('Latitude, longitude and radius must all be provided together.')
                ->atPath($property)
                ->addViolation()
            ;
        }
    }
}
