<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Controller;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Application\UseCase\CreateActivity\CreateActivityCommand;
use App\Activities\Application\UseCase\CreateActivity\CreateActivityHandler;
use App\Activities\Application\UseCase\GetActivity\GetActivityHandler;
use App\Activities\Application\UseCase\GetActivity\GetActivityQuery;
use App\Activities\Application\UseCase\ListGroupActivities\ListGroupActivitiesHandler;
use App\Activities\Application\UseCase\ListGroupActivities\ListGroupActivitiesQuery;
use App\Activities\Application\UseCase\ListReschedules\ListReschedulesHandler;
use App\Activities\Application\UseCase\ListReschedules\ListReschedulesQuery;
use App\Activities\Application\UseCase\UpdateActivity\UpdateActivityCommand;
use App\Activities\Application\UseCase\UpdateActivity\UpdateActivityHandler;
use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Entity\ActivityReschedule;
use App\Activities\Domain\Activity\Repository\ActivityPage;
use App\Activities\Domain\Activity\Repository\ActivitySearchCriteria;
use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityStatus;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Presentation\Http\Request\ActivityListQuery;
use App\Activities\Presentation\Http\Request\CreateActivityRequest;
use App\Activities\Presentation\Http\Request\UpdateActivityRequest;
use App\Activities\Presentation\Http\Response\ActivityRescheduleResponse;
use App\Activities\Presentation\Http\Response\ActivityResponse;
use App\Activities\Presentation\Http\Response\ActivitySummaryResponse;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\ListSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\NotFoundResponse;
use App\Shared\Presentation\Http\OpenApi\PaginatedSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\ResourceConflictResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\UnsupportedMediaTypeResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ApiResponse;
use App\Shared\Presentation\Http\Response\ResultResponder;
use App\Shared\Presentation\Http\Validation\RequestViolationFactory;
use LogicException;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
#[OA\Tag(name: 'Atividades')]
final class ActivityController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-fA-F-]{36}';

    public function __construct(
        private readonly CreateActivityHandler $createActivityHandler,
        private readonly UpdateActivityHandler $updateActivityHandler,
        private readonly GetActivityHandler $getActivityHandler,
        private readonly ListGroupActivitiesHandler $listGroupActivitiesHandler,
        private readonly ListReschedulesHandler $listReschedulesHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        private readonly GroupAccessReaderInterface $groupAccessReader,
        private readonly ActivityAccessPolicy $accessPolicy,
        #[Autowire(service: 'limiter.activity_create')]
        private readonly RateLimiterFactoryInterface $createLimiter,
        #[Autowire(service: 'limiter.activity_manage')]
        private readonly RateLimiterFactoryInterface $manageLimiter,
    ) {
    }

    /**
     * Criar atividade
     *
     * Cria uma atividade vinculada a um grupo ativo ou cheio. Apenas
     * administradores do grupo e administradores SIGAME podem criar.
     */
    #[Route('/groups/{groupId}/activities', name: 'app_activity_create', methods: ['POST'], requirements: ['groupId' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: ActivityResponse::class, response: Response::HTTP_CREATED, description: 'Atividade criada.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ResourceConflictResponse(examples: [
        'ACTIVITY_GROUP_NOT_ACTIVE' => new ApiErrorExample(
            message: 'O grupo precisa ter ao menos 3 membros aprovados para receber atividades.',
            summary: 'Grupo em formação',
            description: 'O grupo ainda não atingiu o mínimo de membros aprovados.',
        ),
    ])]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'O título é obrigatório.',
                field: 'title',
                summary: 'Erros de validação',
                description: 'Um ou mais campos da atividade estão ausentes ou inválidos.',
            ),
        ],
        fields: [
            'title' => 'Título da atividade.',
            'startsAt' => 'Início em ISO 8601.',
            'estimatedDurationMinutes' => 'Duração média em minutos.',
            'meetingPoint' => 'Ponto de encontro.',
            'route' => 'Ponto final ou trajeto.',
            'estimatedDistanceMeters' => 'Distância estimada em metros.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function create(
        string $groupId,
        #[MapRequestPayload] CreateActivityRequest $request,
    ): JsonResponse {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->createLimiter, $actor->value());

        $result = ($this->createActivityHandler)(new CreateActivityCommand(
            groupId: ActivityGroupId::fromString($groupId),
            actorId: $actor,
            isAdminSigame: $this->isGranted('ROLE_ADMIN_SIGAME'),
            title: $request->title,
            description: $request->description,
            startsAt: $request->startsAt,
            estimatedDurationMinutes: $request->estimatedDurationMinutes,
            meetingPoint: $request->meetingPoint,
            route: $request->route,
            estimatedDistanceMeters: $request->estimatedDistanceMeters,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(fn (Activity $activity): ActivityResponse => ActivityResponse::fromEntity(
                $activity,
                $actor,
                true,
            )),
            Response::HTTP_CREATED,
        );
    }

    /**
     * Listar atividades do grupo
     *
     * Lista as atividades visíveis para membros aprovados do grupo.
     */
    #[Route('/groups/{groupId}/activities', name: 'app_activity_list', methods: ['GET'], requirements: ['groupId' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[PaginatedSuccessResponse(itemModel: ActivitySummaryResponse::class, description: 'Lista paginada de atividades.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ValidationErrorResponse(
        examples: [
            'INVALID_CHOICE' => new ApiErrorExample(
                message: 'O estado informado é inválido.',
                field: 'status',
                summary: 'Filtros inválidos',
                description: 'A paginação, o estado ou o intervalo de datas são inválidos.',
            ),
        ],
        fields: [
            'page' => 'Página solicitada.',
            'perPage' => 'Itens por página.',
            'status' => 'Estado da atividade.',
            'from' => 'Início mínimo.',
            'to' => 'Início máximo.',
            'order' => 'Ordem por início (`asc` ou `desc`).',
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function listGroup(
        string $groupId,
        #[MapQueryString(validationFailedStatusCode: 422)] ActivityListQuery $query,
    ): JsonResponse {
        $actor = $this->actorId();
        $criteria = $this->criteriaFrom($query);

        $result = ($this->listGroupActivitiesHandler)(new ListGroupActivitiesQuery(
            groupId: ActivityGroupId::fromString($groupId),
            viewerId: $actor,
            isAdminSigame: $this->isGranted('ROLE_ADMIN_SIGAME'),
            criteria: $criteria,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        /** @var ActivityPage $page */
        $page = $result->value();

        return ApiResponse::ok(array_map(
            fn (Activity $activity): ActivitySummaryResponse => ActivitySummaryResponse::fromEntity(
                $activity,
                $actor,
                $this->canManage($activity, $actor),
            ),
            $page->items,
        ))
            ->withPagination($criteria->page, $criteria->perPage, $page->total)
            ->toJsonResponse();
    }

    /**
     * Obter atividade
     *
     * Retorna os dados da atividade para membros aprovados do grupo.
     */
    #[Route('/activities/{id}', name: 'app_activity_show', methods: ['GET'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: ActivityResponse::class, description: 'Dados da atividade.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[InternalErrorResponse]
    public function show(string $id): JsonResponse
    {
        $actor = $this->actorId();

        $result = ($this->getActivityHandler)(new GetActivityQuery(
            activityId: ActivityId::fromString($id),
            viewerId: $actor,
            isAdminSigame: $this->isGranted('ROLE_ADMIN_SIGAME'),
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(fn (Activity $activity): ActivityResponse => ActivityResponse::fromEntity(
                $activity,
                $actor,
                $this->canManage($activity, $actor),
            )),
        );
    }

    /**
     * Atualizar atividade
     *
     * Atualiza dados e/ou remarca uma atividade agendada. Apenas
     * administradores do grupo e administradores SIGAME podem alterar.
     */
    #[Route('/activities/{id}', name: 'app_activity_update', methods: ['PATCH'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: ActivityResponse::class, description: 'Atividade atualizada.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ResourceConflictResponse(examples: [
        'ACTIVITY_NOT_SCHEDULED' => new ApiErrorExample(
            message: 'A atividade não está agendada e não pode ser alterada.',
            summary: 'Atividade não agendada',
            description: 'Somente atividades agendadas podem ser alteradas.',
        ),
        'ACTIVITY_RESCHEDULE_CONFLICT' => new ApiErrorExample(
            message: 'A remarcação criaria conflito de horário para um ou mais participantes inscritos.',
            summary: 'Conflito na remarcação',
            description: 'Um inscrito possui outra atividade no novo horário.',
        ),
    ])]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'Informe ao menos um campo para atualização.',
                field: 'body',
                summary: 'Erros de validação',
                description: 'O corpo está vazio ou um campo tem formato inválido.',
            ),
        ],
        fields: [
            'body' => 'Corpo da requisição.',
            'title' => 'Título da atividade.',
            'description' => 'Descrição.',
            'meetingPoint' => 'Ponto de encontro.',
            'route' => 'Ponto final ou trajeto.',
            'estimatedDistanceMeters' => 'Distância estimada.',
            'startsAt' => 'Novo início.',
            'estimatedDurationMinutes' => 'Nova duração média.',
            'reason' => 'Motivo da remarcação.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function update(
        string $id,
        #[MapRequestPayload(mapWhenEmpty: true)] UpdateActivityRequest $request,
        HttpRequest $httpRequest,
    ): JsonResponse {
        $payload = $httpRequest->getPayload();

        $has = static fn (string $field): bool => $payload->has($field);

        if (!$has('title') && !$has('description') && !$has('meetingPoint') && !$has('route')
            && !$has('estimatedDistanceMeters') && !$has('startsAt') && !$has('estimatedDurationMinutes')
        ) {
            throw RequestViolationFactory::unprocessable('FIELD_REQUIRED', 'body', 'Informe ao menos um campo para atualização.');
        }

        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->updateActivityHandler)(new UpdateActivityCommand(
            activityId: ActivityId::fromString($id),
            actorId: $actor,
            isAdminSigame: $this->isGranted('ROLE_ADMIN_SIGAME'),
            titleProvided: $has('title'),
            title: $request->title,
            descriptionProvided: $has('description'),
            description: $request->description,
            meetingPointProvided: $has('meetingPoint'),
            meetingPoint: $request->meetingPoint,
            routeProvided: $has('route'),
            route: $request->route,
            distanceProvided: $has('estimatedDistanceMeters'),
            estimatedDistanceMeters: $request->estimatedDistanceMeters,
            startsAtProvided: $has('startsAt'),
            startsAt: $request->startsAt,
            durationProvided: $has('estimatedDurationMinutes'),
            estimatedDurationMinutes: $request->estimatedDurationMinutes,
            reason: $request->reason,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(fn (Activity $activity): ActivityResponse => ActivityResponse::fromEntity(
                $activity,
                $actor,
                $this->canManage($activity, $actor),
            )),
        );
    }

    /**
     * Listar histórico de remarcações
     *
     * Lista as remarcações registradas para a atividade. Apenas
     * administradores do grupo e administradores SIGAME acessam.
     */
    #[Route('/activities/{id}/reschedules', name: 'app_activity_reschedule_list', methods: ['GET'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[ListSuccessResponse(itemModel: ActivityRescheduleResponse::class, description: 'Histórico de remarcações.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[InternalErrorResponse]
    public function listReschedules(string $id): JsonResponse
    {
        $result = ($this->listReschedulesHandler)(new ListReschedulesQuery(
            activityId: ActivityId::fromString($id),
            viewerId: $this->actorId(),
            isAdminSigame: $this->isGranted('ROLE_ADMIN_SIGAME'),
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (array $items): array => array_map(
                static fn (ActivityReschedule $item): ActivityRescheduleResponse => ActivityRescheduleResponse::fromEntity($item),
                $items,
            )),
        );
    }

    private function criteriaFrom(ActivityListQuery $query): ActivitySearchCriteria
    {
        return new ActivitySearchCriteria(
            page: $query->page,
            perPage: $query->perPage,
            status: null === $query->status ? null : ActivityStatus::from($query->status),
            from: $this->instantOrNull($query->from, 'from'),
            to: $this->instantOrNull($query->to, 'to'),
            order: $query->order,
        );
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

    private function canManage(Activity $activity, ActivityUserId $actor): bool
    {
        $group = $this->groupAccessReader->snapshot($activity->groupId()->value(), $actor->value());

        return null === $this->accessPolicy->canManage($group, $this->isGranted('ROLE_ADMIN_SIGAME'));
    }

    private function actorId(): ActivityUserId
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        return ActivityUserId::fromString($user->getUserIdentifier());
    }
}
