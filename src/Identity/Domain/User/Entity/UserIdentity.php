<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Entity;

use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Identity\Domain\User\ValueObject\UserIdentityId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * Identidade de login de um usuário.
 *
 * Um usuário pode ter uma identidade local (e-mail + senha) e identidades
 * sociais (Google, futuramente Apple). O e-mail e a senha deixam de viver em
 * `users`; este agregado é a fonte de verdade para autenticação.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_identities')]
#[ORM\UniqueConstraint(name: 'uniq_user_identities_provider_subject', fields: ['provider', 'providerSubject'])]
#[ORM\Index(name: 'idx_user_identities_email', fields: ['email'])]
final class UserIdentity
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'user_identity_id')]
        private UserIdentityId $id,

        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'identities')]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private User $user,

        #[ORM\Column(type: 'string', length: 20, enumType: IdentityProvider::class)]
        private IdentityProvider $provider,

        #[ORM\Column(type: 'provider_subject', length: 255)]
        private ProviderSubject $providerSubject,

        #[ORM\Column(type: 'email', length: 255, nullable: true)]
        private ?Email $email,

        #[ORM\Column(type: 'hashed_password', length: 255, nullable: true)]
        private ?HashedPassword $password,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $emailVerifiedAt,

        #[ORM\Column(type: 'instant')]
        private Instant $createdAt,
    ) {
    }

    public static function local(
        User $user,
        Email $email,
        HashedPassword $password,
    ): self {
        return new self(
            id: UserIdentityId::generate(),
            user: $user,
            provider: IdentityProvider::LOCAL,
            providerSubject: ProviderSubject::fromString($email->value()),
            email: $email,
            password: $password,
            emailVerifiedAt: null,
            createdAt: Instant::now(),
        );
    }

    public static function social(
        User $user,
        IdentityProvider $provider,
        ProviderSubject $subject,
        Email $email,
        ?Instant $emailVerifiedAt = null,
    ): self {
        if ($provider->isLocal()) {
            throw new InvalidArgumentException('Uma identidade social não pode usar o provedor local.');
        }

        return new self(
            id: UserIdentityId::generate(),
            user: $user,
            provider: $provider,
            providerSubject: $subject,
            email: $email,
            password: null,
            emailVerifiedAt: $emailVerifiedAt,
            createdAt: Instant::now(),
        );
    }

    public function id(): UserIdentityId
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function provider(): IdentityProvider
    {
        return $this->provider;
    }

    public function providerSubject(): ProviderSubject
    {
        return $this->providerSubject;
    }

    public function email(): ?Email
    {
        return $this->email;
    }

    public function password(): ?HashedPassword
    {
        return $this->password;
    }

    public function verifyEmail(Instant $verifiedAt): void
    {
        if (null !== $this->emailVerifiedAt) {
            return;
        }

        $this->emailVerifiedAt = $verifiedAt;
    }

    public function changePassword(HashedPassword $password): void
    {
        $this->password = $password;
    }

    public function emailVerifiedAt(): ?Instant
    {
        return $this->emailVerifiedAt;
    }

    public function isLocal(): bool
    {
        return $this->provider->isLocal();
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }
}
