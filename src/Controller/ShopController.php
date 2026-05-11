<?php

namespace App\Controller;

use App\Dto\ShopInput;
use App\Dto\ShopListQuery;
use App\Entity\Shop;
use App\Entity\User;
use App\Repository\ShopRepository;
use App\Repository\UserRepository;
use App\Service\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Shops')]
#[Route('/api')]
final class ShopController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly Paginator $paginator,
    ) {
    }

    #[Route('/shops', name: 'api_shops_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/shops',
        summary: 'List shops',
        responses: [
            new OA\Response(response: 200, description: 'Shop list'),
        ],
    )]
    public function list(
        ShopRepository $shops,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ShopListQuery $shopListQuery = new ShopListQuery(),
    ): JsonResponse {
        $hydrator = null !== $shopListQuery->latitude
            ? static function (Shop $shop, array $row): void {
                if (isset($row['distance'])) {
                    $shop->distance = (int) round((float) $row['distance']);
                }
            }
        : null;

        $result = $this->paginator->paginate($shops->listQueryBuilder($shopListQuery), null, $hydrator);

        return $this->json($result, Response::HTTP_OK, [], ['groups' => ['paginated', 'shop:list']]);
    }

    #[Route('/shops/{id}', name: 'api_shops_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/shops/{id}',
        summary: 'Get one shop',
        responses: [
            new OA\Response(response: 200, description: 'Shop item'),
            new OA\Response(response: 404, description: 'Shop not found'),
        ],
    )]
    public function get(Shop $shop): JsonResponse
    {
        return $this->json($shop, Response::HTTP_OK, [], ['groups' => ['shop:get']]);
    }

    #[Route('/shops', name: 'api_shops_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/shops',
        summary: 'Create a shop',
        responses: [
            new OA\Response(response: 201, description: 'Shop created'),
            new OA\Response(response: 400, description: 'Invalid payload'),
        ],
    )]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $error = $this->hydrate($shop = new Shop(), $request);

        if (null !== $error) {
            return new JsonResponse(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($shop);
        $entityManager->flush();

        return $this->json($shop, Response::HTTP_CREATED, [], ['groups' => ['shop:get']]);
    }

    #[Route('/shops/{id}', name: 'api_shops_update', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/shops/{id}',
        summary: 'Update a shop',
        responses: [
            new OA\Response(response: 200, description: 'Shop updated'),
            new OA\Response(response: 400, description: 'Invalid payload'),
            new OA\Response(response: 404, description: 'Shop not found'),
        ],
    )]
    public function update(Request $request, Shop $shop, EntityManagerInterface $entityManager): JsonResponse
    {
        $error = $this->hydrate($shop, $request);

        if (null !== $error) {
            return new JsonResponse(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->flush();

        return $this->json($shop, Response::HTTP_OK, [], ['groups' => ['shop:get']]);
    }

    private function hydrate(Shop $shop, Request $request): ?string
    {
        try {
            $input = $this->serializer->deserialize($request->getContent(), ShopInput::class, 'json');
        } catch (SerializerExceptionInterface) {
            return 'Invalid payload.';
        }

        $violations = $this->validator->validate($input);
        if (count($violations) > 0) {
            return $violations[0]->getMessage();
        }

        $manager = $this->users->find($input->managerId);
        if (!$manager instanceof User) {
            return 'Manager not found.';
        }

        $shop
            ->setName((string) $input->name)
            ->setAddress($input->address)
            ->setLatitude($input->latitude)
            ->setLongitude($input->longitude)
            ->setManager($manager)
        ;

        return null;
    }
}
