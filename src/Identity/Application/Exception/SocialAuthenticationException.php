<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

use RuntimeException;

/**
 * Falha na troca/validação do authorization code com o provedor social.
 */
final class SocialAuthenticationException extends RuntimeException
{
    public static function providerRejected(string $provider): self
    {
        return new self(sprintf('O provedor social "%s" recusou a autenticação.', $provider));
    }
}
