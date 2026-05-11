<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ProductListQuery
{
    /**
     * @var string[]
     */
    #[Assert\All([
        new Assert\Regex(pattern: '/^[1-9]\d*$/', message: 'shops must contain positive integer ids only.'),
    ])]
    public array $shops = [];
}
