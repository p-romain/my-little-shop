<?php

namespace App\Service;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * @template T of object
 */
final readonly class PaginatedResult
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        #[Groups(['paginated'])]
        public array $items,
        #[Groups(['paginated'])]
        public int $total,
        #[Groups(['paginated'])]
        public int $pages,
        public int $page,
    ) {
    }
}
