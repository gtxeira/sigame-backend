<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Controller;

use App\Activities\Application\UseCase\CancelEnrollment\CancelEnrollmentCommand;
use App\Activities\Application\UseCase\CancelEnrollment\CancelEnrollmentHandler;
use App\Activities\Application\UseCase\CheckIn\CheckInCommand;
use App\Activities\Application\UseCase\CheckIn\CheckInHandler;
use App\Activities\Application\UseCase\EnrollInActivity\EnrollInActivityCommand;
use App\Activities\Application\UseCase\EnrollInActivity\EnrollInActivityHandler;
use App\Activities\Application\UseCase\ListAttendances\ListAttendancesHandler;
use App\Activities\Application\UseCase\ListAttendances\ListAttendancesQuery;
use App\Activities\Application\UseCase\ValidateAttendance\ValidateAttendanceCommand;
use App\Activities\Application\UseCase\ValidateAttendance\ValidateAttendanceHandler;
use App\Activities\Domain\Activity\Entity\Attendance;
use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Domain\Activity\ValueObject\AttendanceId;
use App\Activities\Presentation\Http\Response\AttendanceResponse;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\ListSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\NotFoundResponse;
use App\Shared\Presentation\Http\OpenApi\ResourceConflictResponse;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
#[OA\Tag(name: 'Atividades')]
final class ActivityParticipationController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-fA-F-]{36}';

    public function __construct(
        private readonly EnrollInActivityHandler $enrollInActivityHandler,
        private readonly CancelEnrollmentHandler $cancelEnrollmentHandler,
        private readonly CheckInHandler $checkInHandler,
        private readonly ValidateAttendanceHandler $validateAttendanceHandler,
        private readonly ListAttendancesHandler $listAttendancesHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.activity_enroll')]
        private readonly RateLimiterFactoryInterface $enrollLimiter,
        #[Autowire(service: 'limiter.activity_manage')]
        private readonly RateLimiterFactoryInterface $manageLimiter,
    ) {
    }

    /**
     * Inscrever-se na atividade
     *
     * Inscreve o usuário autenticado em uma atividade agendada, respeitando o
     * conflito de horário. A operação é idempotente.
     */
    #[Route('/activities/{id}/enrollments', name: 'app_activity_enroll', methods: ['POST'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: AttendanceResponse::class, response: Response::HTTP_CREATED, description: 'Inscrição criada.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ResourceConflictResponse(examples: [
        'ACTIVITY_SCHEDULE_CONFLICT' => new ApiErrorExample(
            message: 'O usuário já possui outra atividade com horário conflitante.',
            summary: 'Conflito de horário',
            description: 'A atividade se sobrepõe a outra inscrição ativa do usuário.',
        ),
        'ACTIVITY_ENROLLMENT_NOT_ALLOWED' => new ApiErrorExample(
            message: 'A inscrição só é permitida em atividades agendadas e antes do início.',
            summary: 'Inscrição encerrada',
            description: 'A atividade já foi iniciada ou não está agendada.',
        ),
    ])]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function enroll(string $id): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->enrollLimiter, $actor->value());

        $result = ($this->enrollInActivityHandler)(new EnrollInActivityCommand(
            activityId: ActivityId::fromString($id),
            userId: $actor,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (Attendance $attendance): AttendanceResponse => AttendanceResponse::fromEntity($attendance)),
            Response::HTTP_CREATED,
        );
    }

    /**
     * Cancelar inscrição
     *
     * Cancela a inscrição do usuário autenticado antes do início da atividade.
     * A operação é idempotente.
     */
    #[Route('/activities/{id}/enrollments/me', name: 'app_activity_enrollment_cancel', methods: ['DELETE'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataProperties: [new OA\Property(property: 'removed', type: 'boolean', example: true)], dataRequired: ['removed'], description: 'Inscrição cancelada.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ResourceConflictResponse(examples: [
        'ACTIVITY_ENROLLMENT_NOT_ALLOWED' => new ApiErrorExample(
            message: 'A inscrição só é permitida em atividades agendadas e antes do início.',
            summary: 'Cancelamento encerrado',
            description: 'A atividade já foi iniciada.',
        ),
    ])]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function cancelEnrollment(string $id): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->enrollLimiter, $actor->value());

        $result = ($this->cancelEnrollmentHandler)(new CancelEnrollmentCommand(
            activityId: ActivityId::fromString($id),
            userId: $actor,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success($result->map(static fn (bool $removed): array => ['removed' => $removed]));
    }

    /**
     * Fazer check-in
     *
     * Registra o check-in do usuário autenticado dentro da janela permitida.
     * A operação é idempotente.
     */
    #[Route('/activities/{id}/check-in', name: 'app_activity_check_in', methods: ['POST'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: AttendanceResponse::class, description: 'Check-in registrado.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ResourceConflictResponse(examples: [
        'ACTIVITY_CHECK_IN_WINDOW_CLOSED' => new ApiErrorExample(
            message: 'O check-in só é permitido de 30 minutos antes do início até 30 minutos após o fim previsto.',
            summary: 'Janela de check-in fechada',
            description: 'O horário atual está fora da janela de check-in.',
        ),
    ])]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function checkIn(string $id): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->enrollLimiter, $actor->value());

        $result = ($this->checkInHandler)(new CheckInCommand(
            activityId: ActivityId::fromString($id),
            userId: $actor,
        ));

        return $this->respondWithAttendance($result);
    }

    /**
     * Listar presenças
     *
     * Lista as inscrições e presenças da atividade. Apenas administradores e
     * supervisores do grupo acessam.
     */
    #[Route('/activities/{id}/attendances', name: 'app_activity_attendance_list', methods: ['GET'], requirements: ['id' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[ListSuccessResponse(itemModel: AttendanceResponse::class, description: 'Presenças da atividade.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[InternalErrorResponse]
    public function listAttendances(string $id): JsonResponse
    {
        $result = ($this->listAttendancesHandler)(new ListAttendancesQuery(
            activityId: ActivityId::fromString($id),
            viewerId: $this->actorId(),
            isAdminSigame: $this->isGranted('ROLE_ADMIN_SIGAME'),
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (array $items): array => array_map(
                static fn (Attendance $attendance): AttendanceResponse => AttendanceResponse::fromEntity($attendance),
                $items,
            )),
        );
    }

    /**
     * Validar presença
     *
     * Valida a presença de um participante após o check-in e a finalização da
     * atividade. Administradores e supervisores do grupo podem validar.
     */
    #[Route('/activities/{id}/attendances/{attendanceId}/validate', name: 'app_activity_attendance_validate', methods: ['POST'], requirements: ['id' => self::UUID_REQUIREMENT, 'attendanceId' => self::UUID_REQUIREMENT])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: AttendanceResponse::class, description: 'Presença validada.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[ResourceConflictResponse(examples: [
        'ACTIVITY_ATTENDANCE_NOT_CHECKED_IN' => new ApiErrorExample(
            message: 'A presença só pode ser validada após o check-in do participante.',
            summary: 'Check-in ausente',
            description: 'O participante ainda não fez check-in.',
        ),
        'ACTIVITY_VALIDATION_WINDOW_CLOSED' => new ApiErrorExample(
            message: 'A validação de presença só é permitida após o fechamento do check-in e em até 48 horas após a finalização.',
            summary: 'Janela de validação fechada',
            description: 'O horário atual está fora da janela de validação.',
        ),
    ])]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function validateAttendance(string $id, string $attendanceId): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->validateAttendanceHandler)(new ValidateAttendanceCommand(
            activityId: ActivityId::fromString($id),
            attendanceId: AttendanceId::fromString($attendanceId),
            actorId: $actor,
            isAdminSigame: $this->isGranted('ROLE_ADMIN_SIGAME'),
        ));

        return $this->respondWithAttendance($result);
    }

    /**
     * @param \App\Shared\Domain\Functional\Result<Attendance> $result
     */
    private function respondWithAttendance(\App\Shared\Domain\Functional\Result $result): JsonResponse
    {
        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (Attendance $attendance): AttendanceResponse => AttendanceResponse::fromEntity($attendance)),
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
