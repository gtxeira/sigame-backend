<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Controller;

use App\Groups\Application\UseCase\ArchiveGroup\ArchiveGroupCommand;
use App\Groups\Application\UseCase\ArchiveGroup\ArchiveGroupHandler;
use App\Groups\Application\UseCase\DeleteGroup\DeleteGroupCommand;
use App\Groups\Application\UseCase\DeleteGroup\DeleteGroupHandler;
use App\Groups\Application\UseCase\UnarchiveGroup\UnarchiveGroupCommand;
use App\Groups\Application\UseCase\UnarchiveGroup\UnarchiveGroupHandler;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Presentation\Http\Request\ArchiveGroupRequest;
use App\Groups\Presentation\Http\Request\DeleteGroupRequest;
use App\Groups\Presentation\Http\Response\GroupResponse;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\NotFoundResponse;
use App\Shared\Presentation\Http\OpenApi\ResourceConflictResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\UnsupportedMediaTypeResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ResultResponder;
use LogicException;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Arquivamento, reativação e exclusão lógica de grupos.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Grupos')]
final class GroupLifecycleController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-fA-F-]{36}';

    public function __construct(
        private readonly ArchiveGroupHandler $archiveGroupHandler,
        private readonly UnarchiveGroupHandler $unarchiveGroupHandler,
        private readonly DeleteGroupHandler $deleteGroupHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.group_manage')]
        private readonly RateLimiterFactoryInterface $manageLimiter,
    ) {
    }

    /**
     * Arquivar grupo
     *
     * Arquiva o grupo, deixando-o em somente leitura e fora da busca. Os
     * membros e o criador são notificados. A operação é idempotente.
     */
    #[Route(
        '/groups/{id}/archive',
        name: 'app_group_archive',
        methods: ['POST'],
        requirements: ['id' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: GroupResponse::class, description: 'Grupo arquivado.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse(
        examples: [
            'GROUP_ADMIN_REQUIRED' => new ApiErrorExample(
                message: 'Apenas administradores do grupo podem executar esta operação.',
                summary: 'Administrador obrigatório',
                description: 'O usuário autenticado não é administrador aprovado do grupo.',
            ),
        ],
    )]
    #[NotFoundResponse]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'O motivo deve ter no máximo 500 caracteres.',
                field: 'reason',
                summary: 'Motivo inválido',
                description: 'O motivo informado é muito longo.',
            ),
        ],
        fields: ['reason' => 'Motivo do arquivamento.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function archive(
        string $id,
        #[MapRequestPayload(mapWhenEmpty: true)] ArchiveGroupRequest $request,
    ): JsonResponse {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->archiveGroupHandler)(new ArchiveGroupCommand(
            groupId: GroupId::fromString($id),
            actorId: $actor,
            reason: $request->reason,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(fn (Group $group): GroupResponse => GroupResponse::fromEntity($group, $actor)),
        );
    }

    /**
     * Reativar grupo
     *
     * Remove o arquivamento do grupo. Apenas administradores podem reativar.
     */
    #[Route(
        '/groups/{id}/unarchive',
        name: 'app_group_unarchive',
        methods: ['POST'],
        requirements: ['id' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: GroupResponse::class, description: 'Grupo reativado.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse(
        examples: [
            'GROUP_ADMIN_REQUIRED' => new ApiErrorExample(
                message: 'Apenas administradores do grupo podem executar esta operação.',
                summary: 'Administrador obrigatório',
                description: 'O usuário autenticado não é administrador aprovado do grupo.',
            ),
        ],
    )]
    #[NotFoundResponse]
    #[ResourceConflictResponse(
        examples: [
            'GROUP_DUPLICATE' => new ApiErrorExample(
                message: 'O usuário já criou um grupo com este nome e modalidade.',
                field: 'name',
                summary: 'Grupo duplicado',
                description: 'Já existe um grupo visível do mesmo criador com o mesmo nome e modalidade.',
            ),
        ],
        fields: ['name' => 'Nome do grupo.'],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function unarchive(string $id): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->unarchiveGroupHandler)(new UnarchiveGroupCommand(
            groupId: GroupId::fromString($id),
            actorId: $actor,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(fn (Group $group): GroupResponse => GroupResponse::fromEntity($group, $actor)),
        );
    }

    /**
     * Excluir grupo
     *
     * Exclui logicamente o grupo. A operação é terminal, preserva o histórico
     * e notifica todos os participantes. Apenas o responsável pode excluir.
     */
    #[Route(
        '/groups/{id}',
        name: 'app_group_delete',
        methods: ['DELETE'],
        requirements: ['id' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(
        dataProperties: [
            new OA\Property(property: 'deleted', type: 'boolean', example: true),
        ],
        dataRequired: ['deleted'],
        description: 'Grupo excluído.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse(
        examples: [
            'GROUP_OWNER_REQUIRED' => new ApiErrorExample(
                message: 'Apenas o responsável pelo grupo pode executar esta operação.',
                summary: 'Responsável obrigatório',
                description: 'O usuário autenticado não é o responsável pelo grupo.',
            ),
        ],
    )]
    #[NotFoundResponse]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'O motivo deve ter no máximo 500 caracteres.',
                field: 'reason',
                summary: 'Motivo inválido',
                description: 'O motivo informado é muito longo.',
            ),
        ],
        fields: ['reason' => 'Motivo da exclusão.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function delete(
        string $id,
        #[MapRequestPayload(mapWhenEmpty: true)] DeleteGroupRequest $request,
    ): JsonResponse {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->deleteGroupHandler)(new DeleteGroupCommand(
            groupId: GroupId::fromString($id),
            actorId: $actor,
            reason: $request->reason,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (bool $deleted): array => ['deleted' => $deleted]),
        );
    }

    private function actorId(): MemberUserId
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        return MemberUserId::fromString($user->getUserIdentifier());
    }
}
