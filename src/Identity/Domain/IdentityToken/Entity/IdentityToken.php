<?php

declare(strict_types=1);

namespace App\Identity\Domain\IdentityToken\Entity;

use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenId;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Token de uso único para verificação de e-mail ou redefinição de senha.
 *
 * Apenas o SHA-256 do valor bruto é persistido; o valor original existe só no
 * link enviado ao usuário.
 */
#[ORM\Entity]
#[ORM\Table(name: 'identity_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_identity_tokens_token_hash', fields: ['tokenHash'])]
#[ORM\Index(name: 'idx_identity_tokens_user_purpose', fields: ['user', 'purpose'])]
final class IdentityToken
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'identity_token_id')]
        private IdentityTokenId $id,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private User $user,

        #[ORM\Column(type: 'string', length: 30, enumType: IdentityTokenPurpose::class)]
        private IdentityTokenPurpose $purpose,

        #[ORM\Column(type: 'string', length: 64)]
        private string $tokenHash,

        #[ORM\Column(type: 'instant')]
        private Instant $expiresAt,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $consumedAt,

        #[ORM\Column(type: 'instant')]
        private Instant $createdAt,
    ) {
    }

    public static function create(
        User $user,
        IdentityTokenPurpose $purpose,
        string $tokenHash,
        Instant $expiresAt,
    ): self {
        return new self(
            id: IdentityTokenId::generate(),
            user: $user,
            purpose: $purpose,
            tokenHash: $tokenHash,
            expiresAt: $expiresAt,
            consumedAt: null,
            createdAt: Instant::now(),
        );
    }

    public function isExpiredAt(Instant $now): bool
    {
        return !$this->expiresAt->isAfter($now);
    }

    public function isConsumed(): bool
    {
        return null !== $this->consumedAt;
    }

    public function matchesPurpose(IdentityTokenPurpose $purpose): bool
    {
        return $this->purpose === $purpose;
    }

    public function consume(Instant $at): void
    {
        if (null !== $this->consumedAt) {
            return;
        }

        $this->consumedAt = $at;
    }

    public function id(): IdentityTokenId
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function purpose(): IdentityTokenPurpose
    {
        return $this->purpose;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): Instant
    {
        return $this->expiresAt;
    }

    public function consumedAt(): ?Instant
    {
        return $this->consumedAt;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }
}
