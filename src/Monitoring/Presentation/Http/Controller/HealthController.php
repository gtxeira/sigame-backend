<?php

declare(strict_types=1);

namespace App\Monitoring\Presentation\Http\Controller;

use App\Monitoring\Infrastructure\Service\SystemHealthCheckerInterface;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\Response\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Monitoring', description: 'Endpoints para verificar o status da aplicação')]
final class HealthController extends AbstractController
{
    public function __construct(
        private readonly SystemHealthCheckerInterface $systemHealthChecker,
    ) {
    }

    /**
     * Verificação de Execução (Liveness Probe).
     *
     * Endpoint leve utilizado como healthcheck para verificar se a aplicação
     * está no ar e respondendo a requisições HTTP.
     */
    #[Route('/api/health/liveness', name: 'app_health_liveness', methods: ['GET'])]
    #[SuccessResponse(
        response: Response::HTTP_OK,
        description: 'Aplicação operando normalmente.',
        dataRequired: ['status'],
        dataProperties: [
            new OA\Property(property: 'status', type: 'string', example: 'UP', const: 'UP'),
        ],
    )]
    public function liveness(): JsonResponse
    {
        return ApiResponse::ok([
            'status' => 'UP',
        ])->toJsonResponse();
    }

    /**
     * Verificação de Prontidão (Readiness Probe).
     *
     * Avalia a saúde geral do sistema e a conectividade com dependências críticas
     * (Banco de Dados, Armazenamento, etc.) para determinar se a aplicação pode processar requisições.
     */
    #[Route('/api/health/readiness', name: 'app_health_readiness', methods: ['GET'])]
    #[SuccessResponse(
        response: Response::HTTP_OK,
        description: 'Aplicação e dependências operando normalmente.',
        dataRequired: ['status', 'checks'],
        dataProperties: [
            new OA\Property(property: 'status', type: 'string', example: 'HEALTHY', const: 'HEALTHY'),
            new OA\Property(
                property: 'checks',
                required: ['database'],
                properties: [
                    new OA\Property(property: 'database', type: 'string', example: 'UP', const: 'UP'),
                ],
                type: 'object',
            ),
        ],
    )]
    #[SuccessResponse(
        response: Response::HTTP_SERVICE_UNAVAILABLE,
        description: 'Serviço degradado ou sem conexão com dependências críticas.',
        dataRequired: ['status', 'checks'],
        dataProperties: [
            new OA\Property(property: 'status', type: 'string', example: 'UNHEALTHY', const: 'UNHEALTHY'),
            new OA\Property(
                property: 'checks',
                required: ['database'],
                properties: [
                    new OA\Property(property: 'database', type: 'string', example: 'DOWN', const: 'DOWN'),
                ],
                type: 'object',
            ),
        ],
    )]
    public function index(): JsonResponse
    {
        $isDatabaseReachable = $this->systemHealthChecker->isDatabaseReachable();
        $isHealthy = $isDatabaseReachable;

        $statusCode = $isHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        $getUpDownStatus = static fn (bool $isReachable) => $isReachable ? 'UP' : 'DOWN';

        return ApiResponse::ok([
            'status' => $isHealthy ? 'HEALTHY' : 'UNHEALTHY',
            'checks' => [
                'database' => $getUpDownStatus($isDatabaseReachable),
            ],
        ])->toJsonResponse($statusCode);
    }
}
