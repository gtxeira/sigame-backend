<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\AuthenticateUser;

use App\Identity\Application\DTO\TokenPair;
use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Email;

/**
 * Autentica com credenciais locais e emite o par de tokens.
 */
final readonly class AuthenticateUserHandler
{
    /**
     * Hash fixo para equalizar o custo do verify quando o e-mail não existe,
     * evitando enumeração de usuários por tempo de resposta.
     */
    private const string DUMMY_HASH = '$2y$12$nTt0pudahZf3SjiV80P5zORv0z7NzlQ.wmGhWR3wyt2dmTnbukuVG';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private TokenPairIssuerInterface $tokenPairIssuer,
    ) {
    }

    /**
     * @return Result<TokenPair>
     */
    public function __invoke(AuthenticateUserCommand $command): Result
    {
        $emailResult = Email::fromString($command->email());

        if ($emailResult->isInvalid()) {
            $this->passwordHasher->verify($command->password(), HashedPassword::fromHash(self::DUMMY_HASH));

            return Result::failure([IdentityErrors::invalidCredentials()]);
        }

        $user = $this->userRepository->findByEmail($emailResult->value());

        if (null === $user) {
            $this->passwordHasher->verify($command->password(), HashedPassword::fromHash(self::DUMMY_HASH));

            return Result::failure([IdentityErrors::invalidCredentials()]);
        }

        $password = $user->password();

        if (null === $password || !$this->passwordHasher->verify($command->password(), $password)) {
            return Result::failure([IdentityErrors::invalidCredentials()]);
        }

        if (!$user->isActive()) {
            return Result::failure([IdentityErrors::accountSuspended()]);
        }

        return Result::success($this->tokenPairIssuer->issueForNewSession($user));
    }
}
