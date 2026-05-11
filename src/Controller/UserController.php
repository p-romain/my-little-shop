<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\Paginator;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Users')]
#[Route('/api')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly Paginator $paginator,
    ) {
    }

    #[Route('/users', name: 'api_users_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users',
        summary: 'List users',
        responses: [
            new OA\Response(response: 200, description: 'User list without password hashes'),
        ],
    )]
    public function list(UserRepository $users): JsonResponse
    {
        $result = $this->paginator->paginate($users->listQueryBuilder());

        return $this->json($result, Response::HTTP_OK, [], ['groups' => ['paginated', 'user:list']]);
    }
}
