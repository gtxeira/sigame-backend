<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Controller;

use App\Groups\Application\UseCase\Admin\GetAdminGroup\GetAdminGroupHandler;
use App\Groups\Application\UseCase\Admin\GetAdminGroup\GetAdminGroupQuery;
use App\Groups\Application\UseCase\Admin\ListAdminGroups\ListAdminGroupsHandler;
use App\Groups\Application\UseCase\Admin\ListAdminGroups\ListAdminGroupsQuery;
use App\Groups\Application\UseCase\ListMemberships\ListMembershipsHandler;
use App\Groups\Application\UseCase\ListMemberships\ListMembershipsQuery;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Repository\GroupPage;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\Repository\GroupSearchCriteria;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Groups\Domain\Group\ValueObject\GroupVisibility;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Presentation\Http\Request\AdminGroupListQuery;
use App\Groups\Presentation\Http\Request\AdminGroupMembersQuery;
use App\Groups\Presentation\Http\Response\AdminGroupResponse;
use App\Groups\Presentation\Http\Response\AdminGroupSummaryResponse;
use App\Groups\Presentation\Http\Response\MembershipResponse;
use App\Shared\Application\User\UserMemberProfileReaderInterface;
use App\Shared\Domain\ValueObject\Modality;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\ListSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\NotFoundResponse;
use App\Shared\Presentation\Http\OpenApi\PaginatedSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ApiResponse;
use App\Shared\Presentation\Http\Response\CsvResponse;
use App\Shared\Presentation\Http\Response\ResultResponder;
use LogicException;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Leitura administrativa de grupos: lista global, detalhe e participações.
 *
 * Inclui arquivados e excluídos. As ações de ciclo de vida continuam nos
 * endpoints de `GroupLifecycleController`, que aceitam `ADMIN_SIGAME` sem
 * vínculo e registram auditoria.
 */
#[Route('/api/v1/admin')]
#[OA\Tag(name: 'Grupos')]
final class AdminGroupController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-fA-F-]{36}';

    public function __construct(
        private readonly ListAdminGroupsHandler $listAdminGroupsHandler,
        private readonly GetAdminGroupHandler $getAdminGroupHandler,
        private readonly ListMembershipsHandler $listMembershipsHandler,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly UserMemberProfileReaderInterface $memberProfiles,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.admin_read')]
        private readonly RateLimiterFactoryInterface $readLimiter,
        #[Autowire(service: 'limiter.admin_export')]
        private readonly RateLimiterFactoryInterface $exportLimiter,
    ) {
    }

    /**
     * Listar grupos para a administração
     *
     * Lista grupos com busca por nome, modalidade, estado e recorte de
     * visibilidade, incluindo arquivados e excluídos.
     */
    #[Route('/groups', name: 'app_admin_group_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[PaginatedSuccessResponse(
        itemModel: AdminGroupSummaryResponse::class,
        description: 'Lista paginada de grupos para a administração.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[ValidationErrorResponse(
        examples: [
            'INVALID_CHOICE' => new ApiErrorExample(
                message: 'A visibilidade informada é inválida.',
                field: 'visibility',
                summary: 'Filtros inválidos',
                description: 'A modalidade, o estado ou a visibilidade são inválidos.',
            ),
        ],
        fields: [
            'page' => 'Página, a partir de 1.',
            'perPage' => 'Itens por página, no máximo 30.',
            'query' => 'Busca parcial pelo nome.',
            'modality' => 'Modalidade do grupo.',
            'status' => 'Estado derivado do grupo.',
            'visibility' => 'Recorte de visibilidade.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function list(#[MapQueryString] AdminGroupListQuery $query): JsonResponse
    {
        $this->rateLimitGuard->assertWithin($this->readLimiter, $this->actorId());

        $result = ($this->listAdminGroupsHandler)(new ListAdminGroupsQuery($this->criteria($query)));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        /** @var GroupPage $page */
        $page = $result->value();
        $profiles = $this->memberProfiles->profiles(array_map(
            static fn (Group $group): string => $group->ownerId()->value(),
            $page->items,
        ));

        return ApiResponse::ok(array_map(
            static fn (Group $group): AdminGroupSummaryResponse => AdminGroupSummaryResponse::fromEntity(
                $group,
                $profiles[$group->ownerId()->value()] ?? null,
            ),
            $page->items,
        ))
            ->withPagination($query->page, $query->perPage, $page->total)
            ->toJsonResponse();
    }

    /**
     * Obter grupo para a administração
     *
     * Retorna os dados administrativos do grupo, inclusive arquivamento e
     * exclusão lógica, sem exigir vínculo.
     */
    #[Route('/groups/{id}', name: 'app_admin_group_show', methods: ['GET'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: AdminGroupResponse::class, description: 'Dados administrativos do grupo.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function show(string $id): JsonResponse
    {
        $this->rateLimitGuard->assertWithin($this->readLimiter, $this->actorId());

        $result = ($this->getAdminGroupHandler)(new GetAdminGroupQuery(GroupId::fromString($id)));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        /** @var Group $group */
        $group = $result->value();
        $profiles = $this->memberProfiles->profiles($this->profileIds($group));

        return $this->resultResponder->success(
            $result->map(static fn (Group $group): AdminGroupResponse => AdminGroupResponse::fromEntity(
                $group,
                $profiles[$group->ownerId()->value()] ?? null,
                null === $group->archivedBy() ? null : ($profiles[$group->archivedBy()->value()] ?? null),
                null === $group->deletedBy() ? null : ($profiles[$group->deletedBy()->value()] ?? null),
            )),
        );
    }

    /**
     * Listar participações de um grupo
     *
     * Lista as participações do grupo por situação, com os dados públicos de
     * cada pessoa, sem exigir vínculo do administrador.
     */
    #[Route('/groups/{id}/members', name: 'app_admin_group_members', methods: ['GET'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[ListSuccessResponse(itemModel: MembershipResponse::class, description: 'Participações do grupo.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ValidationErrorResponse(
        examples: [
            'INVALID_CHOICE' => new ApiErrorExample(
                message: 'A situação informada é inválida.',
                field: 'status',
                summary: 'Filtro inválido',
                description: 'A situação deve ser all, pending, approved, rejected ou removed.',
            ),
        ],
        fields: ['status' => 'Situação da participação.'],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function members(
        string $id,
        #[MapQueryString] AdminGroupMembersQuery $query,
    ): JsonResponse {
        $this->rateLimitGuard->assertWithin($this->readLimiter, $this->actorId());

        $result = ($this->listMembershipsHandler)(new ListMembershipsQuery(
            groupId: GroupId::fromString($id),
            viewerId: $this->actorMemberId(),
            isAdminSigame: true,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        /** @var list<Membership> $memberships */
        $memberships = $result->value();
        $memberships = array_values(array_filter(
            $memberships,
            static fn (Membership $membership): bool => 'all' === $query->status
                || $membership->status()->value === $query->status,
        ));

        $profiles = $this->memberProfiles->profiles(array_map(
            static fn (Membership $membership): string => $membership->userId()->value(),
            $memberships,
        ));

        return ApiResponse::ok(array_map(
            static fn (Membership $membership): MembershipResponse => MembershipResponse::fromEntity(
                $membership,
                $profiles[$membership->userId()->value()] ?? null,
            ),
            $memberships,
        ))->toJsonResponse();
    }

    /**
     * Exportar grupos em CSV
     *
     * Exporta os grupos que atendem aos filtros atuais, incluindo arquivados e
     * excluídos conforme a visibilidade pedida.
     */
    #[Route('/groups/export', name: 'app_admin_group_export', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[OA\Response(
        response: 200,
        description: 'Arquivo CSV com os grupos filtrados.',
        content: new OA\MediaType(
            mediaType: 'text/csv',
            schema: new OA\Schema(type: 'string', format: 'binary'),
        ),
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function export(#[MapQueryString] AdminGroupListQuery $query): StreamedResponse
    {
        $this->rateLimitGuard->assertWithin($this->exportLimiter, $this->actorId());

        return CsvResponse::stream(
            'grupos.csv',
            [
                'id', 'nome', 'modalidade', 'situacao', 'membros_aprovados', 'limite_membros',
                'responsavel_id', 'responsavel_nome', 'criado_em',
                'arquivado_em', 'motivo_arquivamento', 'excluido_em', 'motivo_exclusao',
            ],
            fn (): iterable => $this->groupRows($this->criteria($query)),
        );
    }

    private function criteria(AdminGroupListQuery $query): GroupSearchCriteria
    {
        return new GroupSearchCriteria(
            page: $query->page,
            perPage: $query->perPage,
            modality: null === $query->modality ? null : Modality::from($query->modality),
            status: null === $query->status ? null : GroupStatus::from($query->status),
            name: $query->query,
            visibility: GroupVisibility::from($query->visibility),
        );
    }

    /**
     * @return iterable<list<string|int|null>>
     */
    private function groupRows(GroupSearchCriteria $criteria): iterable
    {
        $page = 1;

        do {
            $result = $this->groupRepository->search(new GroupSearchCriteria(
                page: $page,
                perPage: 30,
                modality: $criteria->modality,
                status: $criteria->status,
                name: $criteria->name,
                visibility: $criteria->visibility,
            ));

            $profiles = $this->memberProfiles->profiles(array_map(
                static fn (Group $group): string => $group->ownerId()->value(),
                $result->items,
            ));

            foreach ($result->items as $group) {
                yield [
                    $group->id()->value(),
                    $group->name()->value(),
                    $group->modality()->value,
                    $group->isDeleted() ? 'excluido' : ($group->isArchived() ? 'arquivado' : $group->status()->value),
                    $group->approvedCount(),
                    $group->maxMembers()?->value(),
                    $group->ownerId()->value(),
                    array_key_exists($group->ownerId()->value(), $profiles)
                        ? $profiles[$group->ownerId()->value()]->name
                        : 'Participante',
                    $group->createdAt()->toIsoStringWithMilliseconds(),
                    $group->archivedAt()?->toIsoStringWithMilliseconds(),
                    $group->archiveReason(),
                    $group->deletedAt()?->toIsoStringWithMilliseconds(),
                    $group->deleteReason(),
                ];
            }

            ++$page;
        } while ($page <= (int) ceil($result->total / 30));
    }

    /**
     * @return list<string>
     */
    private function profileIds(Group $group): array
    {
        $ids = [$group->ownerId()->value()];

        if (null !== $group->archivedBy()) {
            $ids[] = $group->archivedBy()->value();
        }

        if (null !== $group->deletedBy()) {
            $ids[] = $group->deletedBy()->value();
        }

        return array_values(array_unique($ids));
    }

    private function actorId(): string
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        return $user->getUserIdentifier();
    }

    private function actorMemberId(): MemberUserId
    {
        return MemberUserId::fromString($this->actorId());
    }
}
