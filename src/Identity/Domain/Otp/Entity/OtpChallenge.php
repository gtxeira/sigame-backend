<?php

declare(strict_types=1);

namespace App\Identity\Domain\Otp\Entity;

use App\Identity\Domain\Otp\ValueObject\OtpChallengeId;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Desafio OTP de uso único para verificação de e-mail ou redefinição de senha.
 *
 * Apenas o hash do código é persistido; o código original existe só no e-mail
 * enviado ao usuário.
 */
#[ORM\Entity]
#[ORM\Table(name: 'identity_otp_challenges')]
#[ORM\Index(name: 'idx_identity_otp_challenges_user_purpose', fields: ['user', 'purpose'])]
final class OtpChallenge
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'otp_challenge_id')]
        private OtpChallengeId $id,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private User $user,

        #[ORM\Column(type: 'string', length: 30, enumType: OtpPurpose::class)]
        private OtpPurpose $purpose,

        #[ORM\Column(type: 'string', length: 255)]
        private string $codeHash,

        #[ORM\Column(type: 'smallint', options: ['default' => 0])]
        private int $attempts,

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
        OtpPurpose $purpose,
        string $codeHash,
        Instant $expiresAt,
    ): self {
        return new self(
            id: OtpChallengeId::generate(),
            user: $user,
            purpose: $purpose,
            codeHash: $codeHash,
            attempts: 0,
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

    public function hasReachedAttemptLimit(int $maxAttempts): bool
    {
        return $this->attempts >= $maxAttempts;
    }

    /**
     * Registra uma tentativa errada e invalida o desafio ao atingir o limite.
     */
    public function recordFailedAttempt(int $maxAttempts, Instant $now): void
    {
        ++$this->attempts;

        if ($this->attempts >= $maxAttempts) {
            $this->consume($now);
        }
    }

    public function consume(Instant $at): void
    {
        if (null !== $this->consumedAt) {
            return;
        }

        $this->consumedAt = $at;
    }

    public function id(): OtpChallengeId
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function purpose(): OtpPurpose
    {
        return $this->purpose;
    }

    public function codeHash(): string
    {
        return $this->codeHash;
    }

    public function attempts(): int
    {
        return $this->attempts;
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
