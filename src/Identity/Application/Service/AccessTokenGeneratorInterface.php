<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Application\DTO\AccessTokenClaims;
use App\Identity\Application\DTO\IssuedToken;
use App\Identity\Domain\User\ValueObject\UserId;

/**
 * Porta para emissão e validação do access token (JWT).
 */
interface AccessTokenGeneratorInterface
{
    public function issue(UserId $userId): IssuedToken;

    /**
     * @throws \App\Identity\Application\Exception\InvalidTokenException
     */
    public function parse(string $rawToken): AccessTokenClaims;
}
