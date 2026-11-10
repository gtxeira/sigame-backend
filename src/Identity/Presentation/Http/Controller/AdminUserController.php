<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\Admin\AccountStatusChangeResult;
use App\Identity\Application\UseCase\Admin\GetUser\GetUserHandler;
use App\Identity\Application\UseCase\Admin\GetUser\GetUserQuery;
use App\Identity\Application\UseCase\Admin\ListStatusHistory\ListStatusHistoryHandler;
use App\Identity\Application\UseCase\Admin\ListStatusHistory\ListStatusHistoryQuery;
use App\Identity\Application\UseCase\Admin\ListUsers\ListUsersHandler;
use App\Identity\Application\UseCase\Admin\ListUsers\ListUsersQuery;
use App\Identity\Application\UseCase\Admin\ReactivateUser\ReactivateUserCommand;
use App\Identity\Application\UseCase\Admin\ReactivateUser\ReactivateUserHandler;
use App\Identity\Application\UseCase\Admin\SuspendUser\SuspendUserCommand;
use App\Identity\Application\UseCase\Admin\SuspendUser\SuspendUserHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Entity\UserSuspension;
use App\Identity\Domain\User\Repository\UserSearchCriteria;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Identity\Domain\User\ValueObject\UserRole;
use App\Identity\Domain\User\ValueObject\UserStatus;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\AdminStatusHistoryQuery;
use App\Identity\Presentation\Http\Request\AdminUserListQuery;
use App\Identity\Presentation\Http\Request\ReactivateUserRequest;
use App\Identity\Presentation\Http\Request\SuspendUserRequest;
use App\Identity\Presentation\Http\Response\AccountStatusChangeResponse;
use App\Identity\Presentation\Http\Response\AdminUserResponse;
use App\Identity\Presentation\Http\Response\UserSuspensionResponse;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\NotFoundResponse;
use App\Shared\Presentation\Http\OpenApi\PaginatedSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\UnsupportedMediaTypeResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ApiResponse;
use App\Shared\Presentation\Http\Response\ResultResponder;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administração global de contas.
 *
 * Somente administradores SIGAME podem listar, suspender e reativar contas.
 * Contas administrativas não podem ser suspensas por este endpoint.
 */
#[Route('/api/v1/admin')]
#[OA\Tag(name: 'Usuários')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly ListUsersHandler $listUsersHandler,
        private readonly GetUserHandler $getUserHandler,
        private readonly ListStatusHistoryHandler $listStatusHistoryHandler,
        private readonly SuspendUserHandler $suspendUserHandler,
        private readonly ReactivateUserHandler $reactivateUserHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.admin_users_read')]
        private readonly RateLimiterFactoryInterface $readLimiter,
        #[Autowire(service: 'limiter.admin_users_manage')]
        private readonly RateLimiterFactoryInterface $manageLimiter,
    ) {
    }

    /**
     * Listar usuários
     *
     * Lista contas para a administração global, com busca por nome ou e-mail e
     * filtros por estado e papel.
     */
    #[Route('/users', name: 'app_admin_user_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[PaginatedSuccessResponse(
        itemModel: AdminUserResponse::class,
        description: 'Lista paginada de usuários.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['status' => 'O estado informado é inválido.'],
                summary: 'Filtros inválidos',
                description: 'A paginação, o estado ou o papel são inválidos.',
            ),
        ],
        fields: [
            'page' => 'Página, a partir de 1.',
            'perPage' => 'Itens por página, no máximo 30.',
            'query' => 'Busca parcial por nome ou e-mail.',
            'status' => 'Estado da conta.',
            'role' => 'Papel do usuário.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function list(#[MapQueryString] AdminUserListQuery $query): JsonResponse
    {
        $actor = $this->actor();
        $this->rateLimitGuard->assertWithin($this->readLimiter, $actor->value());

        $result = ($this->listUsersHandler)(new ListUsersQuery(new UserSearchCriteria(
            page: $query->page,
            perPage: $query->perPage,
            query: $query->query,
            status: null === $query->status ? null : UserStatus::from($query->status),
            role: null === $query->role ? null : UserRole::from($query->role),
        )));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        $page = $result->value();
        $items = array_map(
            static fn (User $user): AdminUserResponse => AdminUserResponse::fromEntity($user),
            $page->items,
        );

        return ApiResponse::ok($items)
            ->withPagination($query->page, $query->perPage, $page->total)
            ->toJsonResponse();
    }

    /**
     * Obter usuário
     *
     * Retorna os dados administrativos de uma conta.
     */
    #[Route('/users/{id}', name: 'app_admin_user_show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: AdminUserResponse::class, description: 'Dados administrativos da conta.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function show(string $id): JsonResponse
    {
        $actor = $this->actor();
        $this->rateLimitGuard->assertWithin($this->readLimiter, $actor->value());

        $result = ($this->getUserHandler)(new GetUserQuery(UserId::fromString($id)));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (User $user): AdminUserResponse => AdminUserResponse::fromEntity($user)),
        );
    }

    /**
     * Listar histórico de status
     *
     * Lista suspensões e reativações da conta em ordem decrescente.
     */
    #[Route('/users/{id}/status-history', name: 'app_admin_user_status_history', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[PaginatedSuccessResponse(
        itemModel: UserSuspensionResponse::class,
        description: 'Histórico paginado de status da conta.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['page' => 'A página deve ser maior que zero.'],
                summary: 'Paginação inválida',
                description: 'A página ou o tamanho da página são inválidos.',
            ),
        ],
        fields: [
            'page' => 'Página, a partir de 1.',
            'perPage' => 'Itens por página, no máximo 30.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function statusHistory(
        string $id,
        #[MapQueryString] AdminStatusHistoryQuery $query,
    ): JsonResponse {
        $actor = $this->actor();
        $this->rateLimitGuard->assertWithin($this->readLimiter, $actor->value());

        $result = ($this->listStatusHistoryHandler)(new ListStatusHistoryQuery(
            userId: UserId::fromString($id),
            page: $query->page,
            perPage: $query->perPage,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        $page = $result->value();
        $items = array_map(
            static fn (UserSuspension $history): UserSuspensionResponse => UserSuspensionResponse::fromEntity($history),
            $page->items,
        );

        return ApiResponse::ok($items)
            ->withPagination($query->page, $query->perPage, $page->total)
            ->toJsonResponse();
    }

    /**
     * Suspender conta
     *
     * Suspende uma conta comum, registra o motivo e revoga todas as sessões.
     */
    #[Route('/users/{id}/suspend', name: 'app_admin_user_suspend', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: AccountStatusChangeResponse::class, description: 'Conta suspensa.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse(
        examples: [
            'CANNOT_SUSPEND_ADMIN' => new ApiErrorExample(
                message: 'Contas de administração SIGAME não podem ser suspensas por este endpoint.',
                summary: 'Conta administrativa',
                description: 'Contas privilegiadas são administradas por CLI/banco.',
            ),
        ],
    )]
    #[NotFoundResponse]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['reason' => 'O motivo é obrigatório.'],
                summary: 'Motivo inválido',
                description: 'O motivo deve ter entre 3 e 500 caracteres.',
            ),
        ],
        fields: ['reason' => 'Motivo público da suspensão.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function suspend(string $id, #[MapRequestPayload] SuspendUserRequest $request): JsonResponse
    {
        $actor = $this->actor();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->suspendUserHandler)(new SuspendUserCommand(
            userId: UserId::fromString($id),
            actorId: $actor,
            reason: $request->reason,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (AccountStatusChangeResult $change): AccountStatusChangeResponse => AccountStatusChangeResponse::fromResult($change)),
        );
    }

    /**
     * Reativar conta
     *
     * Reativa uma conta suspensa e registra o histórico.
     */
    #[Route('/users/{id}/reactivate', name: 'app_admin_user_reactivate', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: AccountStatusChangeResponse::class, description: 'Conta reativada.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['reason' => 'O motivo deve ter entre 3 e 500 caracteres.'],
                summary: 'Motivo inválido',
                description: 'O motivo, quando informado, deve ter entre 3 e 500 caracteres.',
            ),
        ],
        fields: ['reason' => 'Motivo público da reativação.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function reactivate(string $id, #[MapRequestPayload] ReactivateUserRequest $request): JsonResponse
    {
        $actor = $this->actor();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->reactivateUserHandler)(new ReactivateUserCommand(
            userId: UserId::fromString($id),
            actorId: $actor,
            reason: $request->reason,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (AccountStatusChangeResult $change): AccountStatusChangeResponse => AccountStatusChangeResponse::fromResult($change)),
        );
    }

    private function actor(): UserId
    {
        $user = $this->getUser();
        assert($user instanceof SecurityUser);

        return $user->user()->id();
    }
}
