<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Controller;

use App\Groups\Application\UseCase\ApproveMembership\ApproveMembershipCommand;
use App\Groups\Application\UseCase\ApproveMembership\ApproveMembershipHandler;
use App\Groups\Application\UseCase\ChangeMembershipRole\ChangeMembershipRoleCommand;
use App\Groups\Application\UseCase\ChangeMembershipRole\ChangeMembershipRoleHandler;
use App\Groups\Application\UseCase\LeaveGroup\LeaveGroupCommand;
use App\Groups\Application\UseCase\LeaveGroup\LeaveGroupHandler;
use App\Groups\Application\UseCase\ListMemberships\ListMembershipsHandler;
use App\Groups\Application\UseCase\ListMemberships\ListMembershipsQuery;
use App\Groups\Application\UseCase\RejectMembership\RejectMembershipCommand;
use App\Groups\Application\UseCase\RejectMembership\RejectMembershipHandler;
use App\Groups\Application\UseCase\RemoveMember\RemoveMemberCommand;
use App\Groups\Application\UseCase\RemoveMember\RemoveMemberHandler;
use App\Groups\Application\UseCase\RequestMembership\RequestMembershipCommand;
use App\Groups\Application\UseCase\RequestMembership\RequestMembershipHandler;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MembershipId;
use App\Groups\Domain\Group\ValueObject\MembershipRole;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Presentation\Http\Request\ChangeMembershipRoleRequest;
use App\Groups\Presentation\Http\Request\RemoveMemberRequest;
use App\Groups\Presentation\Http\Response\MembershipResponse;
use App\Shared\Domain\Functional\Result;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\ListSuccessResponse;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Solicitações, aprovação, rejeição e remoção de membros.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Grupos')]
final class GroupMembershipController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-fA-F-]{36}';

    public function __construct(
        private readonly RequestMembershipHandler $requestMembershipHandler,
        private readonly ApproveMembershipHandler $approveMembershipHandler,
        private readonly RejectMembershipHandler $rejectMembershipHandler,
        private readonly RemoveMemberHandler $removeMemberHandler,
        private readonly ChangeMembershipRoleHandler $changeMembershipRoleHandler,
        private readonly ListMembershipsHandler $listMembershipsHandler,
        private readonly LeaveGroupHandler $leaveGroupHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.group_membership_request')]
        private readonly RateLimiterFactoryInterface $requestLimiter,
        #[Autowire(service: 'limiter.group_membership_leave')]
        private readonly RateLimiterFactoryInterface $leaveLimiter,
        #[Autowire(service: 'limiter.group_manage')]
        private readonly RateLimiterFactoryInterface $manageLimiter,
    ) {
    }

    /**
     * Solicitar entrada no grupo
     *
     * Cria uma solicitação pendente, que precisa ser aprovada por um
     * administrador do grupo.
     */
    #[Route(
        '/groups/{id}/membership-requests',
        name: 'app_group_membership_request',
        methods: ['POST'],
        requirements: ['id' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(
        dataModel: MembershipResponse::class,
        response: Response::HTTP_CREATED,
        description: 'Solicitação criada com sucesso.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse(
        examples: [
            'GROUP_LEVEL_INSUFFICIENT' => new ApiErrorExample(
                message: 'É necessário estar no nível 2 ou superior para entrar em um grupo.',
                summary: 'Nível insuficiente',
                description: 'O usuário ainda não concluiu o onboarding ou não alcançou o nível mínimo.',
            ),
        ],
    )]
    #[NotFoundResponse]
    #[ResourceConflictResponse(
        examples: [
            'GROUP_MEMBERSHIP_ALREADY_EXISTS' => new ApiErrorExample(
                message: 'Já existe uma solicitação pendente ou uma participação aprovada neste grupo.',
                summary: 'Solicitação duplicada',
                description: 'O usuário já possui vínculo pendente ou aprovado com o grupo.',
            ),
            'GROUP_ARCHIVED' => new ApiErrorExample(
                message: 'O grupo está arquivado e não aceita esta operação.',
                summary: 'Grupo arquivado',
                description: 'Grupos arquivados não aceitam novas solicitações de entrada.',
            ),
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function request(string $id): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->requestLimiter, $actor->value());

        $result = ($this->requestMembershipHandler)(new RequestMembershipCommand(
            groupId: GroupId::fromString($id),
            actorId: $actor,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (Membership $membership): MembershipResponse => MembershipResponse::fromEntity($membership)),
            Response::HTTP_CREATED,
        );
    }

    /**
     * Sair do grupo
     *
     * Encerra a participação do próprio usuário. Solicitações pendentes são
     * canceladas; participações aprovadas são removidas com motivo de saída
     * voluntária. O último administrador não pode sair. A operação é
     * idempotente: devolve `removed=false` quando não há participação ativa.
     */
    #[Route(
        '/groups/{id}/membership',
        name: 'app_group_membership_leave',
        methods: ['DELETE'],
        requirements: ['id' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(
        dataProperties: [
            new OA\Property(property: 'removed', type: 'boolean', example: true),
        ],
        dataRequired: ['removed'],
        description: 'Participação encerrada.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ResourceConflictResponse(
        examples: [
            'GROUP_LAST_ADMIN_REQUIRED' => new ApiErrorExample(
                message: 'O grupo precisa manter ao menos um administrador aprovado.',
                summary: 'Último administrador',
                description: 'Promova outro membro antes de sair do grupo.',
            ),
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function leave(string $id): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->leaveLimiter, $actor->value());

        $result = ($this->leaveGroupHandler)(new LeaveGroupCommand(
            groupId: GroupId::fromString($id),
            actorId: $actor,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (bool $removed): array => ['removed' => $removed]),
        );
    }

    /**
     * Listar membros do grupo
     *
     * Lista as participações do grupo, incluindo pendentes, aprovadas e o
     * histórico de rejeições e remoções. Apenas administradores acessam.
     */
    #[Route(
        '/groups/{id}/memberships',
        name: 'app_group_membership_list',
        methods: ['GET'],
        requirements: ['id' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[ListSuccessResponse(
        itemModel: MembershipResponse::class,
        description: 'Participações do grupo.',
    )]
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
    #[InternalErrorResponse]
    public function list(string $id): JsonResponse
    {
        $result = ($this->listMembershipsHandler)(new ListMembershipsQuery(
            groupId: GroupId::fromString($id),
            viewerId: $this->actorId(),
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (array $memberships): array => array_map(
                static fn (Membership $membership): MembershipResponse => MembershipResponse::fromEntity($membership),
                $memberships,
            )),
        );
    }

    /**
     * Aprovar solicitação
     *
     * Aprova uma solicitação pendente. Respeita o limite de membros
     * aprovados do grupo e o limite de cinco participações por usuário.
     */
    #[Route(
        '/groups/{id}/memberships/{membershipId}/approve',
        name: 'app_group_membership_approve',
        methods: ['POST'],
        requirements: ['id' => self::UUID_REQUIREMENT, 'membershipId' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: MembershipResponse::class, description: 'Solicitação aprovada.')]
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
            'GROUP_FULL' => new ApiErrorExample(
                message: 'O grupo já possui o número máximo de 5 membros aprovados.',
                summary: 'Grupo cheio',
                description: 'Não há vagas para novos membros aprovados.',
            ),
            'GROUP_LIMIT_REACHED' => new ApiErrorExample(
                message: 'O usuário já participa do número máximo de 5 grupos.',
                summary: 'Limite de grupos do usuário',
                description: 'O usuário solicitante já atingiu o limite de grupos.',
            ),
            'GROUP_ARCHIVED' => new ApiErrorExample(
                message: 'O grupo está arquivado e não aceita esta operação.',
                summary: 'Grupo arquivado',
                description: 'Grupos arquivados não aceitam novas aprovações.',
            ),
            'GROUP_MEMBERSHIP_NOT_PENDING' => new ApiErrorExample(
                message: 'A solicitação não está pendente e não pode ser decidida.',
                summary: 'Solicitação já decidida',
                description: 'A solicitação já foi aprovada, rejeitada ou removida.',
            ),
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function approve(string $id, string $membershipId): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->approveMembershipHandler)(new ApproveMembershipCommand(
            groupId: GroupId::fromString($id),
            membershipId: MembershipId::fromString($membershipId),
            actorId: $actor,
        ));

        return $this->respondWithMembership($result);
    }

    /**
     * Rejeitar solicitação
     *
     * Rejeita uma solicitação pendente. O histórico é preservado.
     */
    #[Route(
        '/groups/{id}/memberships/{membershipId}/reject',
        name: 'app_group_membership_reject',
        methods: ['POST'],
        requirements: ['id' => self::UUID_REQUIREMENT, 'membershipId' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: MembershipResponse::class, description: 'Solicitação rejeitada.')]
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
            'GROUP_MEMBERSHIP_NOT_PENDING' => new ApiErrorExample(
                message: 'A solicitação não está pendente e não pode ser decidida.',
                summary: 'Solicitação já decidida',
                description: 'A solicitação já foi aprovada, rejeitada ou removida.',
            ),
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function reject(string $id, string $membershipId): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->rejectMembershipHandler)(new RejectMembershipCommand(
            groupId: GroupId::fromString($id),
            membershipId: MembershipId::fromString($membershipId),
            actorId: $actor,
        ));

        return $this->respondWithMembership($result);
    }

    /**
     * Remover membro do grupo
     *
     * Remove um membro aprovado e registra autor, data e motivo. O último
     * administrador não pode ser removido.
     */
    #[Route(
        '/groups/{id}/memberships/{membershipId}/remove',
        name: 'app_group_membership_remove',
        methods: ['POST'],
        requirements: ['id' => self::UUID_REQUIREMENT, 'membershipId' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: MembershipResponse::class, description: 'Membro removido do grupo.')]
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
            'GROUP_MEMBERSHIP_NOT_APPROVED' => new ApiErrorExample(
                message: 'A participação não está aprovada.',
                summary: 'Participação não aprovada',
                description: 'Apenas membros aprovados podem ser removidos.',
            ),
            'GROUP_LAST_ADMIN_REQUIRED' => new ApiErrorExample(
                message: 'O grupo precisa manter ao menos um administrador aprovado.',
                summary: 'Último administrador',
                description: 'O administrador informado é o único admin do grupo.',
            ),
        ],
    )]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'O motivo da remoção é obrigatório.',
                field: 'reason',
                summary: 'Motivo inválido',
                description: 'O motivo da remoção está ausente ou é muito longo.',
            ),
        ],
        fields: ['reason' => 'Motivo da remoção.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function remove(
        string $id,
        string $membershipId,
        #[MapRequestPayload] RemoveMemberRequest $request,
    ): JsonResponse {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->removeMemberHandler)(new RemoveMemberCommand(
            groupId: GroupId::fromString($id),
            membershipId: MembershipId::fromString($membershipId),
            actorId: $actor,
            reason: $request->reason,
        ));

        return $this->respondWithMembership($result);
    }

    /**
     * Alterar papel do membro
     *
     * Promove um membro aprovado a administrador ou rebaixa um administrador
     * para membro. O grupo nunca fica sem administrador.
     */
    #[Route(
        '/groups/{id}/memberships/{membershipId}/role',
        name: 'app_group_membership_role',
        methods: ['PATCH'],
        requirements: ['id' => self::UUID_REQUIREMENT, 'membershipId' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: MembershipResponse::class, description: 'Papel do membro atualizado.')]
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
            'GROUP_MEMBERSHIP_NOT_APPROVED' => new ApiErrorExample(
                message: 'A participação não está aprovada.',
                summary: 'Participação não aprovada',
                description: 'Apenas membros aprovados podem ter o papel alterado.',
            ),
            'GROUP_LAST_ADMIN_REQUIRED' => new ApiErrorExample(
                message: 'O grupo precisa manter ao menos um administrador aprovado.',
                summary: 'Último administrador',
                description: 'O administrador informado é o único admin do grupo.',
            ),
            'GROUP_ARCHIVED' => new ApiErrorExample(
                message: 'O grupo está arquivado e não aceita esta operação.',
                summary: 'Grupo arquivado',
                description: 'Grupos arquivados não aceitam alteração de papéis.',
            ),
        ],
    )]
    #[ValidationErrorResponse(
        examples: [
            'INVALID_CHOICE' => new ApiErrorExample(
                message: 'O papel informado é inválido.',
                field: 'role',
                summary: 'Papel inválido',
                description: 'O papel deve ser `admin` ou `member`.',
            ),
        ],
        fields: ['role' => 'Novo papel do membro.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function changeRole(
        string $id,
        string $membershipId,
        #[MapRequestPayload] ChangeMembershipRoleRequest $request,
    ): JsonResponse {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->changeMembershipRoleHandler)(new ChangeMembershipRoleCommand(
            groupId: GroupId::fromString($id),
            membershipId: MembershipId::fromString($membershipId),
            actorId: $actor,
            role: MembershipRole::from($request->role),
        ));

        return $this->respondWithMembership($result);
    }

    /**
     * @param Result<Membership> $result
     */
    private function respondWithMembership(Result $result): JsonResponse
    {
        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (Membership $membership): MembershipResponse => MembershipResponse::fromEntity($membership)),
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
