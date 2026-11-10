<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Xp\ValueObject;

/**
 * Natureza de um lançamento no ledger de XP.
 */
enum XpKind: string
{
    case ONBOARDING = 'onboarding';
    case ACTIVITY = 'activity';
    case WEEKLY_START = 'weekly_start';
    case WEEKLY_GOAL = 'weekly_goal';
    case REVERSAL = 'reversal';
    case INACTIVITY_DECAY = 'inactivity_decay';
    case LEGACY_BONUS = 'legacy_bonus';
    case BACKFILL = 'backfill';

    /**
     * Indica se o lançamento entra no XP vitalício.
     *
     * Decay é a única penalidade que reduz apenas o saldo; o histórico
     * conquistado permanece.
     */
    public function affectsLifetime(): bool
    {
        return self::INACTIVITY_DECAY !== $this;
    }

    public function isPositiveAward(): bool
    {
        return in_array($this, [
            self::ONBOARDING,
            self::ACTIVITY,
            self::WEEKLY_START,
            self::WEEKLY_GOAL,
            self::LEGACY_BONUS,
            self::BACKFILL,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::ONBOARDING => 'Onboarding concluído',
            self::ACTIVITY => 'Atividade validada',
            self::WEEKLY_START => 'Início de semana',
            self::WEEKLY_GOAL => 'Meta semanal',
            self::REVERSAL => 'Estorno',
            self::INACTIVITY_DECAY => 'Perda por inatividade',
            self::LEGACY_BONUS => 'Bônus de fidelidade',
            self::BACKFILL => 'Progresso inicial',
        };
    }
}
