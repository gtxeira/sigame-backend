<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Controller;

use App\Activities\Application\UseCase\Admin\ListAdminActivities\ListAdminActivitiesHandler;
use App\Activities\Application\UseCase\Admin\ListAdminActivities\ListAdminActivitiesQuery;
use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Repository\ActivityPage;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\Repository\ActivitySearchCriteria;
use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityStatus;
use App\Activities\Presentation\Http\Request\AdminActivityListQuery;
use App\Activities\Presentation\Http\Response\AdminActivitySummaryResponse;
use App\Shared\Application\Group\GroupSummaryReaderInterface;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\PaginatedSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ApiResponse;
use App\Shared\Presentation\Http\Response\CsvResponse;
use App\Shared\Presentation\Http\Response\ResultResponder;
use App\Shared\Presentation\Http\Validation\RequestViolationFactory;
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
 * Listagem global de atividades para a administração SIGAME.
 *
 * As ações de moderação continuam nos endpoints de `ActivityLifecycleController`,
 * que já aceitam `ADMIN_SIGAME` em qualquer grupo.
 */
#[Route('/api/v1/admin')]
#[OA\Tag(name: 'Atividades')]
final class AdminActivityController extends AbstractController
{
    public function __construct(
        private readonly ListAdminActivitiesHandler $listAdminActivitiesHandler,
        private readonly ActivityRepositoryInterface $activityRepository,
        private readonly GroupSummaryReaderInterface $groupSummaries,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.admin_read')]
        private readonly RateLimiterFactoryInterface $readLimiter,
        #[Autowire(service: 'limiter.admin_export')]
        private readonly RateLimiterFactoryInterface $exportLimiter,
    ) {
    }

    /**
     * Listar atividades para a administração
     *
     * Lista atividades de todos os grupos com filtros por estado, grupo,
     * título e período.
     */
    #[Route('/activities', name: 'app_admin_activity_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[PaginatedSuccessResponse(
        itemModel: AdminActivitySummaryResponse::class,
        description: 'Lista paginada de atividades para a administração.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[ValidationErrorResponse(
        examples: [
            'INVALID_CHOICE' => new ApiErrorExample(
                message: 'O estado informado é inválido.',
                field: 'status',
                summary: 'Filtros inválidos',
                description: 'O estado, o grupo, o período ou a ordem são inválidos.',
            ),
        ],
        fields: [
            'page' => 'Página, a partir de 1.',
            'perPage' => 'Itens por página, no máximo 30.',
            'status' => 'Estado da atividade.',
            'groupId' => 'Grupo da atividade.',
            'title' => 'Busca parcial pelo título.',
            'from' => 'Início mínimo.',
            'to' => 'Início máximo.',
            'order' => 'Ordem por início.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function list(#[MapQueryString] AdminActivityListQuery $query): JsonResponse
    {
        $this->rateLimitGuard->assertWithin($this->readLimiter, $this->actorId());

        $result = ($this->listAdminActivitiesHandler)(new ListAdminActivitiesQuery($this->criteria($query)));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        /** @var ActivityPage $page */
        $page = $result->value();
        $names = $this->groupSummaries->names(array_map(
            static fn (Activity $activity): string => $activity->groupId()->value(),
            $page->items,
        ));

        return ApiResponse::ok(array_map(
            static fn (Activity $activity): AdminActivitySummaryResponse => AdminActivitySummaryResponse::fromEntity(
                $activity,
                $names[$activity->groupId()->value()] ?? null,
            ),
            $page->items,
        ))
            ->withPagination($query->page, $query->perPage, $page->total)
            ->toJsonResponse();
    }

    /**
     * Exportar atividades em CSV
     *
     * Exporta as atividades que atendem aos filtros atuais, sem paginação.
     */
    #[Route('/activities/export', name: 'app_admin_activity_export', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[OA\Response(
        response: 200,
        description: 'Arquivo CSV com as atividades filtradas.',
        content: new OA\MediaType(
            mediaType: 'text/csv',
            schema: new OA\Schema(type: 'string', format: 'binary'),
        ),
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function export(#[MapQueryString] AdminActivityListQuery $query): StreamedResponse
    {
        $this->rateLimitGuard->assertWithin($this->exportLimiter, $this->actorId());

        return CsvResponse::stream(
            'atividades.csv',
            [
                'id', 'grupo_id', 'grupo_nome', 'titulo', 'inicio', 'fim', 'situacao',
                'inscritos', 'checkins', 'presencas_validadas', 'criado_em',
                'motivo_cancelamento', 'motivo_invalidacao',
            ],
            fn (): iterable => $this->activityRows($this->criteria($query)),
        );
    }

    private function criteria(AdminActivityListQuery $query): ActivitySearchCriteria
    {
        return new ActivitySearchCriteria(
            page: $query->page,
            perPage: $query->perPage,
            status: null === $query->status ? null : ActivityStatus::from($query->status),
            from: $this->instantOrNull($query->from, 'from'),
            to: $this->instantOrNull($query->to, 'to'),
            groupIds: null === $query->groupId ? null : [ActivityGroupId::fromString($query->groupId)->value()],
            title: $query->title,
            order: $query->order,
        );
    }

    /**
     * @return iterable<list<string|int|null>>
     */
    private function activityRows(ActivitySearchCriteria $criteria): iterable
    {
        $page = 1;

        do {
            $result = $this->activityRepository->searchAll(new ActivitySearchCriteria(
                page: $page,
                perPage: 30,
                status: $criteria->status,
                from: $criteria->from,
                to: $criteria->to,
                groupIds: $criteria->groupIds,
                title: $criteria->title,
                order: $criteria->order,
            ));

            $names = $this->groupSummaries->names(array_map(
                static fn (Activity $activity): string => $activity->groupId()->value(),
                $result->items,
            ));

            foreach ($result->items as $activity) {
                yield [
                    $activity->id()->value(),
                    $activity->groupId()->value(),
                    $names[$activity->groupId()->value()] ?? 'Grupo removido',
                    $activity->title()->value(),
                    $activity->window()->startsAt()->toIsoStringWithMilliseconds(),
                    $activity->window()->endsAt()->toIsoStringWithMilliseconds(),
                    $activity->status()->value,
                    $activity->enrolledCount(),
                    $activity->checkedInCount(),
                    $activity->validatedCount(),
                    $activity->createdAt()->toIsoStringWithMilliseconds(),
                    $activity->cancellationReason(),
                    $activity->invalidationReason(),
                ];
            }

            ++$page;
        } while ($page <= (int) ceil($result->total / 30));
    }

    private function instantOrNull(?string $value, string $field): ?Instant
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $instant = Instant::fromString($value);

        if ($instant->isInvalid()) {
            throw RequestViolationFactory::unprocessable('INVALID_FORMAT', $field, 'Data/hora inválida.');
        }

        return $instant->value();
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
