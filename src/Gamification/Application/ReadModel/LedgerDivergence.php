<?php

declare(strict_types=1);

namespace App\Gamification\Application\ReadModel;

/**
 * Divergência entre o ledger e os valores materializados.
 *
 * Os campos de progresso são nulos quando existe lançamento no ledger sem a
 * linha correspondente em `user_progress`.
 */
final readonly class LedgerDivergence
{
    public function __construct(
        public string $userId,
        public ?int $progressBalance,
        public int $ledgerBalance,
        public ?int $progressLifetime,
        public int $ledgerLifetime,
        public ?int $progressLevel,
        public int $ledgerLevel,
        public ?int $projectedLevel,
    ) {
    }

    public function isProgressMissing(): bool
    {
        return null === $this->progressBalance;
    }

    public function balanceIsConsistent(): bool
    {
        return $this->progressBalance === $this->ledgerBalance;
    }

    public function lifetimeIsConsistent(): bool
    {
        return $this->progressLifetime === $this->ledgerLifetime;
    }

    public function levelIsConsistent(): bool
    {
        return $this->progressLevel === $this->ledgerLevel;
    }

    public function projectionIsConsistent(): bool
    {
        return $this->projectedLevel === $this->ledgerLevel;
    }

    public function isConsistent(): bool
    {
        return !$this->isProgressMissing()
            && $this->balanceIsConsistent()
            && $this->lifetimeIsConsistent()
            && $this->levelIsConsistent()
            && $this->projectionIsConsistent();
    }
}
