<?php

declare(strict_types=1);

namespace App\Identity\Domain\RefreshToken\Entity;

use App\Identity\Domain\RefreshToken\ValueObject\RefreshTokenId;
use App\Identity\Domain\RefreshToken\ValueObject\TokenFamilyId;
use App\Identity\Domain\RefreshToken\ValueObject\TokenHash;
use App\Identity\Domain\User\Entity\User;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Refresh token opaco pertencente a uma família de sessão.
 *
 * O token bruto só existe no cookie do cliente; esta entidade guarda o hash
 * SHA-256. A rotação revoga o token atual e cria um novo na mesma família.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_refresh_tokens_token_hash', fields: ['tokenHash'])]
#[ORM\Index(name: 'idx_refresh_tokens_user', fields: ['user'])]
#[ORM\Index(name: 'idx_refresh_tokens_family', fields: ['familyId'])]
final class RefreshToken
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'refresh_token_id')]
        private readonly RefreshTokenId $id,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private readonly User $user,

        #[ORM\Column(type: 'token_hash', length: 64)]
        private readonly TokenHash $tokenHash,

        #[ORM\Column(type: 'token_family_id')]
        private readonly TokenFamilyId $familyId,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $expiresAt,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $createdAt,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $revokedAt = null,

        #[ORM\ManyToOne(targetEntity: self::class)]
        #[ORM\JoinColumn(name: 'replaced_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
        private ?self $replacedBy = null,
    ) {
    }

    public static function create(
        User $user,
        TokenHash $tokenHash,
        TokenFamilyId $familyId,
        Instant $expiresAt,
    ): self {
        return new self(
            id: RefreshTokenId::generate(),
            user: $user,
            tokenHash: $tokenHash,
            familyId: $familyId,
            expiresAt: $expiresAt,
            createdAt: Instant::now(),
            revokedAt: null,
            replacedBy: null,
        );
    }

    public function revoke(?self $replacement = null, ?Instant $at = null): void
    {
        if (null !== $this->revokedAt) {
            return;
        }

        $this->revokedAt = $at ?? Instant::now();
        $this->replacedBy = $replacement;
    }

    public function isExpiredAt(Instant $now): bool
    {
        return !$this->expiresAt->isAfter($now);
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function wasRevokedWithin(Instant $now, int $seconds): bool
    {
        if (null === $this->revokedAt || $seconds <= 0) {
            return false;
        }

        return $this->revokedAt->timestamp() >= $now->timestamp() - $seconds;
    }

    public function belongsToFamily(TokenFamilyId $familyId): bool
    {
        return $this->familyId->equals($familyId);
    }

    public function id(): RefreshTokenId
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function tokenHash(): TokenHash
    {
        return $this->tokenHash;
    }

    public function familyId(): TokenFamilyId
    {
        return $this->familyId;
    }

    public function expiresAt(): Instant
    {
        return $this->expiresAt;
    }

    public function revokedAt(): ?Instant
    {
        return $this->revokedAt;
    }

    public function replacedBy(): ?self
    {
        return $this->replacedBy;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }
}
