<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Porta de enfileiramento dos e-mails transacionais da identidade.
 *
 * O token bruto é entregue ao adaptador, que cifra o payload antes de
 * publicar a mensagem. A entrega real acontece no worker.
 */
interface IdentityMailDispatcherInterface
{
    public function dispatchEmailVerification(
        Email $to,
        string $rawToken,
        Instant $expiresAt,
        string $tokenHash,
    ): void;

    public function dispatchPasswordReset(
        Email $to,
        string $rawToken,
        Instant $expiresAt,
        string $tokenHash,
    ): void;
}
