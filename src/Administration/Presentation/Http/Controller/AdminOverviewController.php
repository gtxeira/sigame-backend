<?php

declare(strict_types=1);

namespace App\Administration\Presentation\Http\Controller;

use App\Administration\Application\Overview\AdminOverview;
use App\Administration\Application\Overview\GetAdminOverviewHandler;
use App\Administration\Presentation\Http\Response\AdminOverviewResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ResultResponder;
use LogicException;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Visão geral consolidada da administração global.
 */
#[Route('/api/v1/admin')]
#[OA\Tag(name: 'Administração')]
final class AdminOverviewController extends AbstractController
{
    public function __construct(
        private readonly GetAdminOverviewHandler $getAdminOverviewHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.admin_read')]
        private readonly RateLimiterFactoryInterface $readLimiter,
    ) {
    }

    /**
     * Obter a visão geral
     *
     * Retorna os contadores de contas, grupos, atividades e check-ins dos
     * últimos sete dias para a operação da plataforma.
     */
    #[Route('/overview', name: 'app_admin_overview', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: AdminOverviewResponse::class, description: 'Contadores consolidados.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function show(): JsonResponse
    {
        $this->rateLimitGuard->assertWithin($this->readLimiter, $this->actorId());

        $result = ($this->getAdminOverviewHandler)();

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (AdminOverview $overview): AdminOverviewResponse => AdminOverviewResponse::fromOverview($overview)),
        );
    }

    private function actorId(): string
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        return $user->getUserIdentifier();
    }
}
