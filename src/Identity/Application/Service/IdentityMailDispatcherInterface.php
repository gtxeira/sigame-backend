<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Domain\Otp\ValueObject\OtpChallengeId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Porta de enfileiramento dos e-mails transacionais da identidade.
 *
 * O código bruto é entregue ao adaptador, que cifra o payload antes de
 * publicar a mensagem. A entrega real acontece no worker.
 */
interface IdentityMailDispatcherInterface
{
    public function dispatchEmailVerification(
        Email $to,
        string $code,
        Instant $expiresAt,
        OtpChallengeId $challengeId,
    ): void;

    public function dispatchPasswordReset(
        Email $to,
        string $code,
        Instant $expiresAt,
        OtpChallengeId $challengeId,
    ): void;

    /**
     * Avisa o dono de uma conta que alguém tentou cadastrar o mesmo e-mail.
     *
     * Não leva código nem link de acesso; apenas instruções para entrar ou
     * recuperar a senha.
     */
    public function dispatchAccountExists(Email $to): void;
}
