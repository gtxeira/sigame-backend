<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Application\DTO\TokenPair;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\RefreshToken\Entity\RefreshToken;
use App\Identity\Domain\RefreshToken\Repository\RefreshTokenRepositoryInterface;
use App\Identity\Domain\RefreshToken\ValueObject\TokenFamilyId;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;

/**
 * Emite, rotaciona e revoga o par access token + refresh token.
 *
 * O access token é JWT; o refresh token é opaco e só existe em texto no cookie.
 * O banco é a fonte de verdade para expiração, revogação e família.
 */
final readonly class TokenPairIssuer implements TokenPairIssuerInterface
{
    public function __construct(
        private AccessTokenGeneratorInterface $accessTokenGenerator,
        private RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private LockFactory $lockFactory,
        private ClockInterface $clock,
        #[Autowire('%env(int:REFRESH_TOKEN_TTL)%')]
        private int $refreshTtlSeconds = 604800,
        #[Autowire('%env(int:REFRESH_TOKEN_REUSE_GRACE_SECONDS)%')]
        private int $reuseGraceSeconds = 30,
        #[Autowire('%env(int:REFRESH_TOKEN_MAX_FAMILIES)%')]
        private int $maxFamilies = 5,
    ) {
    }

    public function issueForNewSession(User $user): TokenPair
    {
        $familyId = TokenFamilyId::generate();
        $generated = $this->refreshTokenGenerator->generate();
        $expiresAt = $this->expiresIn($this->refreshTtlSeconds);

        $token = RefreshToken::create(
            user: $user,
            tokenHash: $generated->hash,
            familyId: $familyId,
            expiresAt: $expiresAt,
        );

        $this->refreshTokenRepository->save($token);
        $this->refreshTokenRepository->keepNewestFamilies($user->id(), $this->maxFamilies);

        return new TokenPair(
            accessToken: $this->accessTokenGenerator->issue($user->id()),
            refreshToken: $generated->raw,
            refreshExpiresAt: $expiresAt,
        );
    }

    /**
     * @return Result<TokenPair>
     */
    public function rotate(string $rawRefreshToken): Result
    {
        $hash = $this->refreshTokenGenerator->hash($rawRefreshToken);
        $lock = $this->lockFactory->createLock('refresh-token-'.$hash->value(), 10.0);

        if (!$lock->acquire()) {
            return Result::failure([IdentityErrors::refreshInProgress()]);
        }

        try {
            $current = $this->refreshTokenRepository->findByTokenHash($hash);

            if (null === $current) {
                return Result::failure([IdentityErrors::refreshTokenInvalid()]);
            }

            $now = Instant::fromDateTime($this->clock->now());

            if ($current->isExpiredAt($now)) {
                $current->revoke(at: $now);
                $this->refreshTokenRepository->save($current);

                return Result::failure([IdentityErrors::refreshTokenExpired()]);
            }

            if ($current->isRevoked()) {
                if ($current->wasRevokedWithin($now, $this->reuseGraceSeconds)) {
                    return Result::failure([IdentityErrors::refreshTokenAlreadyRotated()]);
                }

                $this->refreshTokenRepository->revokeFamily($current->familyId());

                return Result::failure([IdentityErrors::refreshTokenReused()]);
            }

            $user = $current->user();

            if (!$user->isActive()) {
                $this->refreshTokenRepository->revokeFamily($current->familyId());

                return Result::failure([IdentityErrors::accountSuspended()]);
            }

            $generated = $this->refreshTokenGenerator->generate();
            $expiresAt = $this->expiresIn($this->refreshTtlSeconds);

            $replacement = RefreshToken::create(
                user: $user,
                tokenHash: $generated->hash,
                familyId: $current->familyId(),
                expiresAt: $expiresAt,
            );

            $current->revoke($replacement, $now);
            $this->refreshTokenRepository->rotate($current, $replacement);

            return Result::success(new TokenPair(
                accessToken: $this->accessTokenGenerator->issue($user->id()),
                refreshToken: $generated->raw,
                refreshExpiresAt: $expiresAt,
            ));
        } finally {
            $lock->release();
        }
    }

    public function revokeCurrentSession(string $rawRefreshToken): void
    {
        $token = $this->refreshTokenRepository->findByTokenHash(
            $this->refreshTokenGenerator->hash($rawRefreshToken),
        );

        if (null === $token) {
            return;
        }

        $this->refreshTokenRepository->revokeFamily($token->familyId());
    }

    public function revokeAllSessions(UserId $userId): void
    {
        $this->refreshTokenRepository->revokeAllForUser($userId);
    }

    private function expiresIn(int $seconds): Instant
    {
        return Instant::fromDateTime($this->clock->now()->modify(sprintf('+%d seconds', $seconds)));
    }
}
