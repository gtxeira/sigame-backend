<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Social\CompleteSocialLogin;

/**
 * Callback de login social: code + state devolvidos pelo provedor.
 */
final readonly class CompleteSocialLoginCommand
{
    public function __construct(
        private string $provider,
        private string $flowId,
        private string $state,
        private string $authorizationCode,
    ) {
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function flowId(): string
    {
        return $this->flowId;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function authorizationCode(): string
    {
        return $this->authorizationCode;
    }
}
