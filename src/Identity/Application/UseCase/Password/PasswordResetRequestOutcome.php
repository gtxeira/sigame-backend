<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Password;

/**
 * Resultado do pedido de redefinição de senha.
 *
 * Quando o cooldown por conta está ativo, o pedido não gera código nem e-mail e
 * a resposta informa quantos segundos faltam para uma nova solicitação.
 */
final readonly class PasswordResetRequestOutcome
{
    private function __construct(
        public ?int $retryAfterSeconds,
    ) {
    }

    /**
     * Pedido aceito: código criado e enfileirado (ou e-mail inexistente, que é
     * indistinguível de propósito).
     */
    public static function requested(): self
    {
        return new self(null);
    }

    /**
     * Pedido recente: nenhum novo e-mail é enviado antes do fim do cooldown.
     */
    public static function throttled(int $retryAfterSeconds): self
    {
        return new self(max(1, $retryAfterSeconds));
    }
}
