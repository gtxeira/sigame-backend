<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Controller;

use App\Activities\Application\UseCase\CancelActivity\CancelActivityCommand;
use App\Activities\Application\UseCase\CancelActivity\CancelActivityHandler;
use App\Activities\Application\UseCase\FinishActivity\FinishActivityCommand;
use App\Activities\Application\UseCase\FinishActivity\FinishActivityHandler;
use App\Activities\Application\UseCase\InvalidateActivity\InvalidateActivityCommand;
use App\Activities\Application\UseCase\InvalidateActivity\InvalidateActivityHandler;
use App\Activities\Application\UseCase\StartActivity\StartActivityCommand;
use App\Activities\Application\UseCase\StartActivity\StartActivityHandler;
use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Presentation\Http\Request\CancelActivityRequest;
use App\Activities\Presentation\Http\Request\InvalidateActivityRequest;
use App\Activities\Presentation\Http\Response\ActivityResponse;
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

#[Route('/api/v1')]
#[OA\Tag(name: 'Atividades')]
final class ActivityLifecycleController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-fA-F-]{36}';

    public function __construct(
        private readonly CancelActivityHandler $cancelActivityHandler,
        private readonly StartActivityHandler $startActivityHandler,
        private readonly FinishActivityHandler $finishActivityHandler,
        private readonly InvalidateActivityHandler $invalidateActivityHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.activity_manage')]
        private readonly RateLimiterFactoryInterface $manageLimiter,
    ) {
    }

    /**
     * Cancelar atividade
     *
     * Cancela uma atividade agendada. Apenas administradores do grupo e
     * administradores SIGAME podem cancelar.
     */
    #[Route('/activities/{id}/cancel', name: 'app_activity_cancel', methods: ['POST'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: ActivityResponse::class, description: 'Atividade cancelada.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ResourceConflictResponse(examples: [
        'ACTIVITY_NOT_SCHEDULED' => new ApiErrorExample(
            message: 'A atividade não está agendada e não pode ser alterada.',
            summary: 'Atividade não agendada',
            description: 'Somente atividades agendadas podem ser canceladas.',
        ),
    ])]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['reason' => 'O motivo deve ter no máximo 500 caracteres.'],
                summary: 'Motivo inválido',
                description: 'O motivo do cancelamento está fora do limite.',
            ),
        ],
        fields: ['reason' => 'Motivo do cancelamento.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function cancel(
        string $id,
        #[MapRequestPayload(mapWhenEmpty: true)] CancelActivityRequest $request,
    ): JsonResponse {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        return $this->respond(($this->cancelActivityHandler)(new CancelActivityCommand(
            activityId: ActivityId::fromString($id),
            actorId: $actor,
            isAdminSigame: $this->isGranted('ROLE_ADMIN_SIGAME'),
            reason: $request->reason,
        )), $actor);
    }

    /**
     * Iniciar atividade
     *
     * Inicia uma atividade dentro da janela operacional. Administradores e
     * supervisores do grupo podem iniciar.
     */
    #[Route('/activities/{id}/start', name: 'app_activity_start', methods: ['POST'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: ActivityResponse::class, description: 'Atividade iniciada.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ResourceConflictResponse(examples: [
        'ACTIVITY_START_WINDOW_CLOSED' => new ApiErrorExample(
            message: 'A atividade só pode ser iniciada entre 15 minutos antes e 2 horas após o horário previsto.',
            summary: 'Janela de início fechada',
            description: 'O horário atual está fora da janela operacional.',
        ),
    ])]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function start(string $id): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        return $this->respond(($this->startActivityHandler)(new StartActivityCommand(
            activityId: ActivityId::fromString($id),
            actorId: $actor,
            isAdminSigame: $this->isGranted('ROLE_ADMIN_SIGAME'),
        )), $actor);
    }

    /**
     * Finalizar atividade
     *
     * Finaliza uma atividade iniciada. Administradores e supervisores do grupo
     * podem finalizar.
     */
    #[Route('/activities/{id}/finish', name: 'app_activity_finish', methods: ['POST'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: ActivityResponse::class, description: 'Atividade finalizada.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ResourceConflictResponse(examples: [
        'ACTIVITY_NOT_STARTED' => new ApiErrorExample(
            message: 'A atividade ainda não foi iniciada.',
            summary: 'Atividade não iniciada',
            description: 'Somente atividades iniciadas podem ser finalizadas.',
        ),
        'ACTIVITY_FINISH_WINDOW_CLOSED' => new ApiErrorExample(
            message: 'A atividade só pode ser finalizada em até 24 horas após o início.',
            summary: 'Janela de finalização fechada',
            description: 'O prazo de finalização expirou.',
        ),
    ])]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function finish(string $id): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        return $this->respond(($this->finishActivityHandler)(new FinishActivityCommand(
            activityId: ActivityId::fromString($id),
            actorId: $actor,
            isAdminSigame: $this->isGranted('ROLE_ADMIN_SIGAME'),
        )), $actor);
    }

    /**
     * Invalidar atividade
     *
     * Invalida uma atividade iniciada ou finalizada. Apenas administradores do
     * grupo e administradores SIGAME podem invalidar, com motivo obrigatório.
     */
    #[Route('/activities/{id}/invalidate', name: 'app_activity_invalidate', methods: ['POST'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: ActivityResponse::class, description: 'Atividade invalidada.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ResourceConflictResponse(examples: [
        'ACTIVITY_INVALIDATION_WINDOW_CLOSED' => new ApiErrorExample(
            message: 'O prazo para invalidar a atividade expirou.',
            summary: 'Janela de invalidação fechada',
            description: 'A atividade não pode mais ser invalidada.',
        ),
    ])]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['reason' => 'O motivo é obrigatório.'],
                summary: 'Motivo inválido',
                description: 'O motivo da invalidação está ausente ou fora do limite.',
            ),
        ],
        fields: ['reason' => 'Motivo da invalidação.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function invalidate(
        string $id,
        #[MapRequestPayload] InvalidateActivityRequest $request,
    ): JsonResponse {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        return $this->respond(($this->invalidateActivityHandler)(new InvalidateActivityCommand(
            activityId: ActivityId::fromString($id),
            actorId: $actor,
            isAdminSigame: $this->isGranted('ROLE_ADMIN_SIGAME'),
            reason: $request->reason,
        )), $actor);
    }

    /**
     * @param \App\Shared\Domain\Functional\Result<Activity> $result
     */
    private function respond(\App\Shared\Domain\Functional\Result $result, ActivityUserId $actor): JsonResponse
    {
        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (Activity $activity): ActivityResponse => ActivityResponse::fromEntity(
                $activity,
                $actor,
                true,
            )),
        );
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
