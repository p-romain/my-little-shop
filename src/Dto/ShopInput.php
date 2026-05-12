<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ShopInput
{
    #[Assert\NotBlank(message: 'Name is required.')]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Address is required.')]
    public ?string $address = null;

    #[Assert\NotNull(message: 'Latitude is required.')]
    public ?float $latitude = null;

    #[Assert\NotNull(message: 'Longitude is required.')]
    public ?float $longitude = null;

    #[Assert\NotNull(message: 'Manager is required.')]
    #[Assert\Positive(message: 'Manager is required.')]
    public ?int $managerId = null;
}
