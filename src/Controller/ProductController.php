<?php

namespace App\Controller;

use App\Dto\ProductListQuery;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use App\Service\Paginator;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Products')]
#[Route('/api')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly Paginator $paginator,
    ) {
    }

    #[Route('/products', name: 'api_products_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/products',
        summary: 'List products',
        responses: [
            new OA\Response(response: 200, description: 'Product list'),
        ],
    )]
    public function list(
        ProductRepository $products,
        StockRepository $stocks,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ProductListQuery $productListQuery = new ProductListQuery(),
    ): JsonResponse {
        $shopIds = array_map(intval(...), $productListQuery->shops);
        $result = $this->paginator->paginate($products->listQueryBuilder($shopIds));

        $productIds = array_map(fn (Product $p): int => $p->getId(), $result->items);
        $stocksByProduct = $stocks->findByProductIds($productIds, $shopIds);
        foreach ($result->items as $product) {
            $product->stocks = $stocksByProduct[$product->getId()] ?? [];
        }

        return $this->json($result, Response::HTTP_OK, [], ['groups' => ['paginated', 'product:list']]);
    }
}
