<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\DTO\AccessTokenClaims;
use App\Identity\Application\DTO\IssuedToken;
use App\Identity\Application\Exception\InvalidTokenException;
use App\Identity\Application\Service\AccessTokenGeneratorInterface;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Instant;
use DateTimeImmutable;
use InvalidArgumentException;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\JwtFacade;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Emissão e validação do access token JWT (HS256).
 */
final readonly class LcobucciAccessTokenGenerator implements AccessTokenGeneratorInterface
{
    private InMemory $key;
    private Sha256 $signer;

    public function __construct(
        #[Autowire(env: 'ACCESS_TOKEN_SECRET')]
        private string $secret,
        #[Autowire(env: 'ACCESS_TOKEN_ISSUER')]
        private string $issuer,
        #[Autowire(env: 'ACCESS_TOKEN_AUDIENCE')]
        private string $audience,
        #[Autowire(env: 'int:ACCESS_TOKEN_TTL')]
        private int $ttlSeconds,
        private ClockInterface $clock,
    ) {
        if (mb_strlen($this->secret) < 32) {
            throw new InvalidArgumentException('ACCESS_TOKEN_SECRET deve ter pelo menos 32 caracteres.');
        }

        $this->key = InMemory::plainText($this->secret);
        $this->signer = new Sha256();
    }

    public function issue(UserId $userId): IssuedToken
    {
        $issuedAt = $this->clock->now();
        $expiresAt = $issuedAt->modify(sprintf('+%d seconds', $this->ttlSeconds));
        $tokenId = bin2hex(random_bytes(16));

        $token = new JwtFacade(clock: $this->clock)->issue(
            $this->signer,
            $this->key,
            fn (Builder $builder, DateTimeImmutable $now): Builder => $builder
                ->issuedBy($this->issuer)
                ->permittedFor($this->audience)
                ->relatedTo($userId->value())
                ->identifiedBy($tokenId)
                ->withClaim('typ', 'access')
                ->issuedAt($issuedAt)
                ->canOnlyBeUsedAfter($issuedAt)
                ->expiresAt($expiresAt),
        );

        return new IssuedToken(
            token: $token->toString(),
            expiresAt: Instant::fromDateTime($expiresAt),
        );
    }

    public function parse(string $rawToken): AccessTokenClaims
    {
        try {
            $token = new JwtFacade(clock: $this->clock)->parse(
                $rawToken,
                new Constraint\SignedWith($this->signer, $this->key),
                new Constraint\StrictValidAt($this->clock),
                new Constraint\IssuedBy($this->issuer),
                new Constraint\PermittedFor($this->audience),
                new Constraint\HasClaimWithValue('typ', 'access'),
            );

            $subject = $token->claims()->get('sub');
            $expiresAt = $token->claims()->get('exp');

            if (!is_string($subject) || !$expiresAt instanceof DateTimeImmutable) {
                throw InvalidTokenException::accessToken('claims obrigatórios ausentes');
            }

            return new AccessTokenClaims(
                userId: UserId::fromString($subject),
                expiresAt: Instant::fromDateTime($expiresAt),
            );
        } catch (InvalidTokenException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw InvalidTokenException::accessToken($exception->getMessage());
        }
    }
}
