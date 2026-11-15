<?php

declare(strict_types=1);

namespace App\Identity\Domain\PasswordReset\Entity;

use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\PasswordReset\ValueObject\PasswordResetTicketId;
use App\Identity\Domain\User\Entity\User;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ticket temporário emitido depois da validação do código de reset.
 *
 * Autoriza uma única troca de senha por um curto período. O valor bruto só
 * existe no cliente; o banco guarda o SHA-256.
 */
#[ORM\Entity]
#[ORM\Table(name: 'identity_password_reset_tickets')]
#[ORM\UniqueConstraint(name: 'uniq_identity_password_reset_tickets_token_hash', fields: ['tokenHash'])]
#[ORM\Index(name: 'idx_identity_password_reset_tickets_user', fields: ['user'])]
final class PasswordResetTicket
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'password_reset_ticket_id')]
        private PasswordResetTicketId $id,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private User $user,

        #[ORM\ManyToOne(targetEntity: OtpChallenge::class)]
        #[ORM\JoinColumn(name: 'challenge_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private OtpChallenge $challenge,

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
        OtpChallenge $challenge,
        string $tokenHash,
        Instant $expiresAt,
    ): self {
        return new self(
            id: PasswordResetTicketId::generate(),
            user: $user,
            challenge: $challenge,
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

    public function consume(Instant $at): void
    {
        if (null !== $this->consumedAt) {
            return;
        }

        $this->consumedAt = $at;
    }

    public function id(): PasswordResetTicketId
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function challenge(): OtpChallenge
    {
        return $this->challenge;
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
