<?php

namespace App\Service;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpFoundation\RequestStack;

final class Paginator
{
    public const PAGE_SIZE = 30;

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * @template T of object
     *
     * @param QueryBuilder                                 $qb          a builder yielding T entities (optionally with extra scalar columns); do not call setFirstResult/setMaxResults on it
     * @param callable(T, array<string, mixed>): void|null $rowHydrator called with (entity, row) when extra scalars are selected
     *
     * @return PaginatedResult<T>
     */
    public function paginate(QueryBuilder $qb, ?int $page = null, ?callable $rowHydrator = null): PaginatedResult
    {
        $page = max(1, $page ?? $this->pageFromRequest());

        $query = (clone $qb)
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()
        ;

        $paginator = new DoctrinePaginator($query);
        $total = count($paginator);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));

        $items = array_map(
            static function (mixed $row) use ($rowHydrator): object {
                if (is_array($row) && array_key_exists(0, $row)) {
                    $entity = $row[0];
                    if (null !== $rowHydrator) {
                        $rowHydrator($entity, $row);
                    }

                    return $entity;
                }

                return $row;
            },
            iterator_to_array($paginator),
        );

        /** @var PaginatedResult<T> $result */
        $result = new PaginatedResult(
            items: array_values($items),
            total: $total,
            pages: $pages,
            page: $page,
        );

        return $result;
    }

    private function pageFromRequest(): int
    {
        $request = $this->requestStack->getCurrentRequest();

        return null === $request ? 1 : max(1, $request->query->getInt('page', 1));
    }
}
