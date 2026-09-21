<?php

namespace App\Controller;

use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    /**
     * Verifica a saúde do sistema.
     *
     * Retorna o status do serviço.
     */
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Sistema operacional',
        content: new OA\JsonContent(
            example: [
                'data' => 'ok',
                'meta' => [
                    'timestamp' => '2026-09-21T10:00:00.000+00:00',
                ],
            ],
            oneOf: [
                new OA\Schema(ref: '#/components/schemas/ApiResponseSuccess'),
            ],
        ),
    )]
    #[OA\Tag(name: 'Health')]
    public function index(): JsonResponse
    {
        return ApiResponse::ok('ok')->toJsonResponse();
    }
}
