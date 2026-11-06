<?php

declare(strict_types=1);

namespace App\Identity\Application\DTO;

/**
 * Resultado do callback de login social.
 *
 * Ou o usuário foi autenticado e recebeu um par de tokens, ou a conta já
 * existe com senha local e precisa de um vínculo explícito. O ticket não é
 * um erro de domínio: ele orienta o cliente a repetir o vínculo após o login.
 */
final readonly class SocialLoginOutcome
{
    private function __construct(
        public ?TokenPair $tokens,
        public ?IssuedLinkTicket $linkTicket,
    ) {
    }

    public static function authenticated(TokenPair $tokens): self
    {
        return new self($tokens, null);
    }

    public static function linkRequired(IssuedLinkTicket $ticket): self
    {
        return new self(null, $ticket);
    }

    public function requiresLink(): bool
    {
        return null !== $this->linkTicket;
    }
}
