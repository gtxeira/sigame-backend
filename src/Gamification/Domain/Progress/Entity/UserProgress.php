<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Progress\Entity;

use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Rule\GamificationRules;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * Progresso agregado do usuário.
 *
 * O saldo e o nível são materializações do ledger `xp_events`; o XP vitalício
 * preserva o histórico e não é afetado por deleveling.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_progress')]
#[ORM\Index(name: 'idx_user_progress_decay', columns: ['last_validated_activity_at'])]
final class UserProgress
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'player_id', unique: true)]
        private readonly PlayerId $userId,

        #[ORM\Column(type: 'integer', options: ['default' => 0])]
        private int $xpBalance,

        #[ORM\Column(type: 'integer', options: ['default' => 0])]
        private int $xpLifetime,

        #[ORM\Column(type: 'smallint', options: ['default' => GamificationRules::MIN_LEVEL])]
        private int $level,

        #[ORM\Column(type: 'integer', options: ['default' => 0])]
        private int $currentWeeklyStreak,

        #[ORM\Column(type: 'integer', options: ['default' => 0])]
        private int $bestWeeklyStreak,

        #[ORM\Column(type: 'string', length: 10, nullable: true)]
        private ?string $lastCompletedWeek,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $lastValidatedActivityAt,

        #[ORM\Column(type: 'string', length: 10, nullable: true)]
        private ?string $lastDecayPeriodEnd,

        #[ORM\Column(type: 'integer', options: ['default' => 0])]
        private int $decayedPeriods,

        #[ORM\Column(type: 'string', length: 32)]
        private string $ruleVersion,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $createdAt,

        #[ORM\Column(type: 'instant')]
        private Instant $updatedAt,
    ) {
    }

    public static function create(PlayerId $userId): self
    {
        $now = Instant::now();

        return new self(
            userId: $userId,
            xpBalance: 0,
            xpLifetime: 0,
            level: GamificationRules::MIN_LEVEL,
            currentWeeklyStreak: 0,
            bestWeeklyStreak: 0,
            lastCompletedWeek: null,
            lastValidatedActivityAt: null,
            lastDecayPeriodEnd: null,
            decayedPeriods: 0,
            ruleVersion: GamificationRules::VERSION,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function award(int $amount, bool $affectsLifetime): void
    {
        $this->xpBalance = max(0, $this->xpBalance + $amount);

        if ($affectsLifetime) {
            $this->xpLifetime = max(0, $this->xpLifetime + $amount);
        }

        $this->touch();
    }

    /**
     * Corrige os valores materializados a partir do ledger.
     *
     * Uso administrativo: não concede nem retira XP do histórico. O reparo deve
     * ser decidido fora do domínio, a partir da auditoria de `xp_events`.
     */
    public function reconcile(int $xpBalance, int $xpLifetime): void
    {
        if ($xpBalance < 0 || $xpLifetime < 0) {
            throw new LogicException('Saldo e XP vitalício não podem ser negativos.');
        }

        $this->xpBalance = $xpBalance;
        $this->xpLifetime = $xpLifetime;
        $this->touch();
    }

    public function decay(int $amount): void
    {
        $this->xpBalance = max(
            0,
            $this->xpBalance - max(0, $amount),
        );
        $this->touch();
    }

    public function changeLevel(int $level): bool
    {
        if ($level === $this->level) {
            return false;
        }

        $this->level = $level;
        $this->touch();

        return true;
    }

    public function recordActivityValidation(Instant $validatedAt): void
    {
        $this->lastValidatedActivityAt = $validatedAt;
        $this->lastDecayPeriodEnd = null;
        $this->decayedPeriods = 0;
        $this->touch();
    }

    public function recordWeeklyStreak(int $current, int $best, ?string $lastCompletedWeek): void
    {
        $this->currentWeeklyStreak = max(0, $current);
        $this->bestWeeklyStreak = max($this->bestWeeklyStreak, $best);
        $this->lastCompletedWeek = $lastCompletedWeek;
        $this->touch();
    }

    public function recordDecayPeriod(string $periodEnd): void
    {
        $this->lastDecayPeriodEnd = $periodEnd;
        ++$this->decayedPeriods;
        $this->touch();
    }

    public function userId(): PlayerId
    {
        return $this->userId;
    }

    public function xpBalance(): int
    {
        return $this->xpBalance;
    }

    public function xpLifetime(): int
    {
        return $this->xpLifetime;
    }

    public function level(): int
    {
        return $this->level;
    }

    public function currentWeeklyStreak(): int
    {
        return $this->currentWeeklyStreak;
    }

    public function bestWeeklyStreak(): int
    {
        return $this->bestWeeklyStreak;
    }

    public function lastCompletedWeek(): ?string
    {
        return $this->lastCompletedWeek;
    }

    public function lastValidatedActivityAt(): ?Instant
    {
        return $this->lastValidatedActivityAt;
    }

    public function lastDecayPeriodEnd(): ?string
    {
        return $this->lastDecayPeriodEnd;
    }

    public function decayedPeriods(): int
    {
        return $this->decayedPeriods;
    }

    public function ruleVersion(): string
    {
        return $this->ruleVersion;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = Instant::now();
    }
}
