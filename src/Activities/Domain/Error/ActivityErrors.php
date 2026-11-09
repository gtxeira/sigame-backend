<?php

declare(strict_types=1);

namespace App\Activities\Domain\Error;

use App\Shared\Domain\Error\DomainError;

/**
 * Fábricas de erros de domínio esperados do módulo Activities.
 */
final class ActivityErrors
{
    public static function activityNotFound(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityNotFound,
            message: 'A atividade não foi encontrada.',
        );
    }

    public static function groupNotFound(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityGroupNotFound,
            message: 'O grupo da atividade não foi encontrado.',
            field: 'groupId',
        );
    }

    public static function attendanceNotFound(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityAttendanceNotFound,
            message: 'A presença não foi encontrada.',
        );
    }

    public static function enrollmentNotFound(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityEnrollmentNotFound,
            message: 'A inscrição não foi encontrada.',
        );
    }

    public static function titleInvalid(int $minimum, int $maximum): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityTitleInvalid,
            message: sprintf('O título da atividade deve ter entre %d e %d caracteres.', $minimum, $maximum),
            field: 'title',
        );
    }

    public static function descriptionInvalid(int $maximum): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityDescriptionInvalid,
            message: sprintf('A descrição da atividade deve ter no máximo %d caracteres.', $maximum),
            field: 'description',
        );
    }

    public static function meetingPointInvalid(int $minimum, int $maximum): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityMeetingPointInvalid,
            message: sprintf('O ponto de encontro deve ter entre %d e %d caracteres.', $minimum, $maximum),
            field: 'meetingPoint',
        );
    }

    public static function routeInvalid(int $minimum, int $maximum): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityRouteInvalid,
            message: sprintf('O trajeto ou ponto final deve ter entre %d e %d caracteres.', $minimum, $maximum),
            field: 'route',
        );
    }

    public static function estimatedDistanceInvalid(int $minimum, int $maximum): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityEstimatedDistanceInvalid,
            message: sprintf('A distância estimada deve estar entre %d e %d metros.', $minimum, $maximum),
            field: 'estimatedDistanceMeters',
        );
    }

    public static function windowInvalid(int $minimum, int $maximum): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityWindowInvalid,
            message: sprintf('A duração estimada deve estar entre %d e %d minutos e o início deve ser anterior ao fim.', $minimum, $maximum),
            field: 'startsAt',
        );
    }

    public static function startInPast(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityStartInPast,
            message: 'O início da atividade deve estar no futuro.',
            field: 'startsAt',
        );
    }

    public static function cancelReasonInvalid(int $maximum): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityCancelReasonInvalid,
            message: sprintf('O motivo do cancelamento deve ter no máximo %d caracteres.', $maximum),
            field: 'reason',
        );
    }

    public static function rescheduleReasonInvalid(int $maximum): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityRescheduleReasonInvalid,
            message: sprintf('O motivo da remarcação deve ter no máximo %d caracteres.', $maximum),
            field: 'reason',
        );
    }

    public static function invalidationReasonRequired(int $minimum, int $maximum): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityInvalidationReasonRequired,
            message: sprintf('O motivo da invalidação é obrigatório e deve ter entre %d e %d caracteres.', $minimum, $maximum),
            field: 'reason',
        );
    }

    public static function permissionDenied(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityPermissionDenied,
            message: 'Você não possui permissão para executar esta operação na atividade.',
        );
    }

    public static function groupMembershipRequired(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityGroupMembershipRequired,
            message: 'Apenas membros aprovados do grupo podem acessar as atividades.',
        );
    }

    public static function enrollmentRequired(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityEnrollmentRequired,
            message: 'É necessário estar inscrito na atividade para realizar esta operação.',
        );
    }

    public static function groupNotActive(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityGroupNotActive,
            message: 'O grupo precisa ter ao menos 3 membros aprovados para receber atividades.',
            field: 'groupId',
        );
    }

    public static function notScheduled(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityNotScheduled,
            message: 'A atividade não está agendada e não pode ser alterada.',
        );
    }

    public static function notStarted(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityNotStarted,
            message: 'A atividade ainda não foi iniciada.',
        );
    }

    public static function notFinished(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityNotFinished,
            message: 'A atividade ainda não foi finalizada.',
        );
    }

    public static function cancelled(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityCancelled,
            message: 'A atividade está cancelada e não pode ser alterada.',
        );
    }

    public static function invalidated(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityInvalidated,
            message: 'A atividade está invalidada e não pode ser alterada.',
        );
    }

    public static function startWindowClosed(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityStartWindowClosed,
            message: 'A atividade só pode ser iniciada entre 15 minutos antes e 2 horas após o horário previsto.',
        );
    }

    public static function finishWindowClosed(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityFinishWindowClosed,
            message: 'A atividade só pode ser finalizada em até 24 horas após o início.',
        );
    }

    public static function invalidationWindowClosed(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityInvalidationWindowClosed,
            message: 'O prazo para invalidar a atividade expirou.',
        );
    }

    public static function enrollmentNotAllowed(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityEnrollmentNotAllowed,
            message: 'A inscrição só é permitida em atividades agendadas e antes do início.',
        );
    }

    public static function enrollmentNotActive(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityEnrollmentNotActive,
            message: 'A inscrição não está ativa.',
        );
    }

    public static function alreadyEnrolled(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityAlreadyEnrolled,
            message: 'Você já está inscrito nesta atividade.',
        );
    }

    public static function checkInWindowClosed(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityCheckInWindowClosed,
            message: 'O check-in só é permitido de 30 minutos antes do início até 30 minutos após o fim previsto.',
        );
    }

    public static function attendanceNotCheckedIn(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityAttendanceNotCheckedIn,
            message: 'A presença só pode ser validada após o check-in do participante.',
        );
    }

    public static function scheduleConflict(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityScheduleConflict,
            message: 'O usuário já possui outra atividade com horário conflitante.',
            field: 'startsAt',
        );
    }

    public static function rescheduleConflict(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityRescheduleConflict,
            message: 'A remarcação criaria conflito de horário para um ou mais participantes inscritos.',
            field: 'startsAt',
        );
    }

    public static function validationWindowClosed(): DomainError
    {
        return self::error(
            code: ActivityErrorCode::ActivityValidationWindowClosed,
            message: 'A validação de presença só é permitida após o fechamento do check-in e em até 48 horas após a finalização.',
        );
    }

    private static function error(
        ActivityErrorCode $code,
        string $message,
        ?string $field = null,
    ): DomainError {
        return new DomainError(
            code: $code->value,
            message: $message,
            kind: $code->kind(),
            field: $field,
        );
    }
}
