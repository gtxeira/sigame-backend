<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Domain\Notification\ValueObject\NotificationType;
use App\Shared\Domain\ValueObject\Instant;
use DateTimeZone;

/**
 * Renderiza título, corpo e payload de cada tipo de notificação.
 *
 * O conteúdo é PT-BR e independe do frontend; o payload carrega apenas IDs e
 * dados públicos para deep link.
 */
final readonly class NotificationContentFactory
{
    private const string TIMEZONE = 'America/Sao_Paulo';

    public function __construct(
        private NotificationAudienceResolver $audience,
    ) {
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    public function create(NotificationType $type, array $context): ?NotificationContent
    {
        return match ($type) {
            NotificationType::MEMBERSHIP_REQUESTED => $this->membershipRequested($context),
            NotificationType::MEMBERSHIP_APPROVED => $this->membershipApproved($context),
            NotificationType::MEMBERSHIP_REJECTED => $this->membershipRejected($context),
            NotificationType::MEMBER_REMOVED => $this->memberRemoved($context),
            NotificationType::MEMBERSHIP_PROMOTED => $this->membershipPromoted($context),
            NotificationType::MEMBERSHIP_DEMOTED => $this->membershipDemoted($context),
            NotificationType::SUPERVISOR_DESIGNATED => $this->supervisorDesignated($context),
            NotificationType::SUPERVISOR_REMOVED => $this->supervisorRemoved($context),
            NotificationType::GROUP_ACTIVATED => $this->groupActivated($context),
            NotificationType::ACTIVITY_CREATED => $this->activityCreated($context),
            NotificationType::ACTIVITY_UPDATED => $this->activityUpdated($context),
            NotificationType::ACTIVITY_RESCHEDULED => $this->activityRescheduled($context),
            NotificationType::ACTIVITY_CANCELLED => $this->activityCancelled($context),
            NotificationType::ACTIVITY_INVALIDATED => $this->activityInvalidated($context),
            NotificationType::ACTIVITY_FINISHED => $this->activityFinished($context),
            NotificationType::ATTENDANCE_VALIDATED => $this->attendanceValidated($context),
            NotificationType::LEVEL_REACHED => $this->levelReached($context),
            NotificationType::LEVEL_LOST => $this->levelLost($context),
            NotificationType::WEEKLY_GOAL_COMPLETED => $this->weeklyGoalCompleted($context),
            NotificationType::ACCOUNT_SUSPENDED => $this->accountSuspended($context),
            NotificationType::ACCOUNT_REACTIVATED => $this->accountReactivated($context),
        };
    }

    public function formatInstant(Instant $instant): string
    {
        return $instant->value()
            ->setTimezone(new DateTimeZone(self::TIMEZONE))
            ->format('d/m/Y \à\s H:i');
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function membershipRequested(array $context): ?NotificationContent
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        if (null === $group) {
            return null;
        }

        $requesterName = $this->audience->userName($this->string($context, 'requesterId')) ?? 'Um usuário';

        return new NotificationContent(
            title: 'Nova solicitação de entrada',
            body: sprintf('%s quer entrar no grupo %s.', $requesterName, $group->name),
            payload: [
                'groupId' => $group->id,
                'membershipId' => $this->string($context, 'membershipId'),
                'requesterId' => $this->string($context, 'requesterId'),
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function membershipApproved(array $context): ?NotificationContent
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        if (null === $group) {
            return null;
        }

        return new NotificationContent(
            title: 'Entrada aprovada',
            body: sprintf('Sua entrada no grupo %s foi aprovada.', $group->name),
            payload: [
                'groupId' => $group->id,
                'membershipId' => $this->string($context, 'membershipId'),
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function membershipRejected(array $context): ?NotificationContent
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        if (null === $group) {
            return null;
        }

        return new NotificationContent(
            title: 'Solicitação recusada',
            body: sprintf('Sua solicitação para entrar no grupo %s foi recusada.', $group->name),
            payload: [
                'groupId' => $group->id,
                'membershipId' => $this->string($context, 'membershipId'),
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function memberRemoved(array $context): ?NotificationContent
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        if (null === $group) {
            return null;
        }

        $reason = $this->nullableString($context, 'reason');
        $body = sprintf('Você foi removido do grupo %s.', $group->name);

        if (null !== $reason && '' !== $reason) {
            $body .= sprintf(' Motivo: %s', $reason);
        }

        return new NotificationContent(
            title: 'Você foi removido de um grupo',
            body: $body,
            payload: [
                'groupId' => $group->id,
                'membershipId' => $this->string($context, 'membershipId'),
                'reason' => $reason,
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function membershipPromoted(array $context): ?NotificationContent
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        if (null === $group) {
            return null;
        }

        return new NotificationContent(
            title: 'Novo papel no grupo',
            body: sprintf('Você agora é administrador do grupo %s.', $group->name),
            payload: [
                'groupId' => $group->id,
                'membershipId' => $this->string($context, 'membershipId'),
                'role' => 'ADMIN',
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function membershipDemoted(array $context): ?NotificationContent
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        if (null === $group) {
            return null;
        }

        return new NotificationContent(
            title: 'Novo papel no grupo',
            body: sprintf('Você agora é membro do grupo %s.', $group->name),
            payload: [
                'groupId' => $group->id,
                'membershipId' => $this->string($context, 'membershipId'),
                'role' => 'MEMBER',
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function supervisorDesignated(array $context): ?NotificationContent
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        if (null === $group) {
            return null;
        }

        return new NotificationContent(
            title: 'Você é supervisor',
            body: sprintf('Você foi designado supervisor do grupo %s.', $group->name),
            payload: [
                'groupId' => $group->id,
                'membershipId' => $this->string($context, 'membershipId'),
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function supervisorRemoved(array $context): ?NotificationContent
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        if (null === $group) {
            return null;
        }

        return new NotificationContent(
            title: 'Supervisão encerrada',
            body: sprintf('Você não é mais supervisor do grupo %s.', $group->name),
            payload: [
                'groupId' => $group->id,
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function groupActivated(array $context): ?NotificationContent
    {
        $group = $this->audience->group($this->string($context, 'groupId'));

        if (null === $group) {
            return null;
        }

        return new NotificationContent(
            title: 'Grupo ativo',
            body: sprintf('O grupo %s agora está ativo e pode receber atividades.', $group->name),
            payload: ['groupId' => $group->id],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function activityCreated(array $context): ?NotificationContent
    {
        $activity = $this->audience->activity($this->string($context, 'activityId'));
        $group = null === $activity ? null : $this->audience->group($activity->groupId);

        if (null === $activity || null === $group) {
            return null;
        }

        return new NotificationContent(
            title: 'Nova atividade',
            body: sprintf(
                '%s no grupo %s, em %s.',
                $activity->title,
                $group->name,
                $this->formatInstant($activity->startsAt),
            ),
            payload: [
                'activityId' => $activity->id,
                'groupId' => $group->id,
                'startsAt' => $activity->startsAt->toIsoStringWithMilliseconds(),
                'automatic' => false,
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function activityUpdated(array $context): ?NotificationContent
    {
        $activity = $this->audience->activity($this->string($context, 'activityId'));

        if (null === $activity) {
            return null;
        }

        return new NotificationContent(
            title: 'Atividade atualizada',
            body: sprintf('Os dados da atividade %s foram atualizados.', $activity->title),
            payload: [
                'activityId' => $activity->id,
                'groupId' => $activity->groupId,
                'automatic' => false,
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function activityRescheduled(array $context): ?NotificationContent
    {
        $activity = $this->audience->activity($this->string($context, 'activityId'));

        if (null === $activity) {
            return null;
        }

        $newStartsAt = $activity->startsAt;
        $previous = $this->nullableString($context, 'previousStartsAt');
        $previousLabel = null === $previous ? 'o horário anterior' : $this->formatIsoString($previous);

        return new NotificationContent(
            title: 'Atividade remarcada',
            body: sprintf(
                'A atividade %s foi remarcada de %s para %s.',
                $activity->title,
                $previousLabel,
                $this->formatInstant($newStartsAt),
            ),
            payload: [
                'activityId' => $activity->id,
                'groupId' => $activity->groupId,
                'previousStartsAt' => $previous,
                'newStartsAt' => $newStartsAt->toIsoStringWithMilliseconds(),
                'automatic' => false,
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function activityCancelled(array $context): ?NotificationContent
    {
        $activity = $this->audience->activity($this->string($context, 'activityId'));

        if (null === $activity) {
            return null;
        }

        $reason = $this->nullableString($context, 'reason');
        $body = sprintf('A atividade %s foi cancelada.', $activity->title);

        if (null !== $reason && '' !== $reason) {
            $body .= sprintf(' Motivo: %s', $reason);
        }

        if ($this->bool($context, 'automatic')) {
            $body .= ' O cancelamento foi automático.';
        }

        return new NotificationContent(
            title: 'Atividade cancelada',
            body: $body,
            payload: [
                'activityId' => $activity->id,
                'groupId' => $activity->groupId,
                'reason' => $reason,
                'automatic' => $this->bool($context, 'automatic'),
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function activityInvalidated(array $context): ?NotificationContent
    {
        $activity = $this->audience->activity($this->string($context, 'activityId'));

        if (null === $activity) {
            return null;
        }

        $reason = $this->nullableString($context, 'reason');
        $body = sprintf('A atividade %s foi invalidada.', $activity->title);

        if (null !== $reason && '' !== $reason) {
            $body .= sprintf(' Motivo: %s', $reason);
        }

        return new NotificationContent(
            title: 'Atividade invalidada',
            body: $body,
            payload: [
                'activityId' => $activity->id,
                'groupId' => $activity->groupId,
                'reason' => $reason,
                'automatic' => false,
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function activityFinished(array $context): ?NotificationContent
    {
        $activity = $this->audience->activity($this->string($context, 'activityId'));

        if (null === $activity) {
            return null;
        }

        $body = sprintf('A atividade %s foi finalizada. Valide as presenças pendentes.', $activity->title);

        if ($this->bool($context, 'automatic')) {
            $body .= ' A finalização foi automática.';
        }

        return new NotificationContent(
            title: 'Presenças pendentes',
            body: $body,
            payload: [
                'activityId' => $activity->id,
                'groupId' => $activity->groupId,
                'automatic' => $this->bool($context, 'automatic'),
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function attendanceValidated(array $context): ?NotificationContent
    {
        $activity = $this->audience->activity($this->string($context, 'activityId'));

        if (null === $activity) {
            return null;
        }

        return new NotificationContent(
            title: 'Presença validada',
            body: sprintf('Sua presença na atividade %s foi validada.', $activity->title),
            payload: [
                'activityId' => $activity->id,
                'groupId' => $activity->groupId,
                'attendanceId' => $this->string($context, 'attendanceId'),
                'xpEligible' => $this->bool($context, 'xpEligible'),
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function levelReached(array $context): NotificationContent
    {
        $level = $this->int($context, 'level');

        return new NotificationContent(
            title: 'Novo nível',
            body: sprintf('Você alcançou o nível %d.', $level),
            payload: [
                'previousLevel' => $this->int($context, 'previousLevel'),
                'level' => $level,
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function levelLost(array $context): NotificationContent
    {
        $level = $this->int($context, 'level');

        return new NotificationContent(
            title: 'Nível reduzido',
            body: sprintf('Você caiu para o nível %d.', $level),
            payload: [
                'previousLevel' => $this->int($context, 'previousLevel'),
                'level' => $level,
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function weeklyGoalCompleted(array $context): NotificationContent
    {
        return new NotificationContent(
            title: 'Meta semanal concluída',
            body: sprintf(
                'Você completou a meta semanal e ganhou %d XP. Sequência: %d semana(s).',
                $this->int($context, 'xp'),
                $this->int($context, 'streak'),
            ),
            payload: [
                'weekKey' => $this->string($context, 'weekKey'),
                'streak' => $this->int($context, 'streak'),
                'xp' => $this->int($context, 'xp'),
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function accountSuspended(array $context): NotificationContent
    {
        $reason = $this->nullableString($context, 'reason') ?? 'não informado';

        return new NotificationContent(
            title: 'Conta suspensa',
            body: sprintf('Sua conta no SIGAME foi suspensa. Motivo: %s.', $reason),
            payload: [
                'suspensionId' => $this->string($context, 'suspensionId'),
                'reason' => $reason,
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function accountReactivated(array $context): NotificationContent
    {
        return new NotificationContent(
            title: 'Conta reativada',
            body: 'Sua conta no SIGAME foi reativada. Você já pode entrar novamente.',
            payload: [
                'suspensionId' => $this->string($context, 'suspensionId'),
            ],
        );
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function string(array $context, string $key): string
    {
        $value = $context[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function nullableString(array $context, string $key): ?string
    {
        $value = $context[$key] ?? null;

        if (null === $value || '' === $value) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function int(array $context, string $key): int
    {
        $value = $context[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    private function bool(array $context, string $key): bool
    {
        return (bool) ($context[$key] ?? false);
    }

    private function formatIsoString(string $value): string
    {
        $instant = Instant::fromString($value);

        if ($instant->isInvalid()) {
            return 'o horário anterior';
        }

        return $this->formatInstant($instant->value());
    }
}
