<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use App\Identity\Domain\Exception\OnboardingAlreadyCompletedException;
use App\Identity\Domain\Exception\OnboardingInvalidPrimaryModalityException;
use App\Identity\Domain\Exception\OnboardingNoModalitySelectedException;
use App\Identity\Domain\Exception\UserAlreadyActiveException;
use App\Identity\Domain\Exception\UserAlreadySuspendedException;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\OnboardingStatus;
use App\Identity\Domain\ValueObject\UserId;
use App\Identity\Domain\ValueObject\UserLevel;
use App\Identity\Domain\ValueObject\UserProfile;
use App\Identity\Domain\ValueObject\UserRole;
use App\Identity\Domain\ValueObject\UserStatus;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entidade User com comportamento de domínio rico.
 *
 * Representa um usuário do sistema com todas as regras de negócio
 * relacionadas a cadastro, onboarding e status da conta.
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
final class User
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'user_id', unique: true)]
        private readonly UserId $id,

        #[ORM\Column(type: 'cpf', length: 11, unique: true)]
        private readonly Cpf $cpf,

        #[ORM\Column(type: 'email', length: 255, unique: true)]
        private Email $email,

        #[ORM\Column(type: 'hashed_password', length: 255)]
        private HashedPassword $password,

        #[ORM\Column(type: 'string', length: 255)]
        private string $name,

        #[ORM\Column(type: 'string', length: 10, enumType: UserRole::class)]
        private UserRole $role,

        #[ORM\Column(type: 'user_level')]
        private UserLevel $level,

        #[ORM\Column(type: 'string', length: 20, enumType: OnboardingStatus::class)]
        private OnboardingStatus $onboardingStatus,

        #[ORM\Column(type: 'string', length: 20, enumType: UserStatus::class)]
        private UserStatus $status,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $createdAt,

        #[ORM\Column(type: 'json', nullable: true)]
        private ?UserProfile $profile = null,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $updatedAt = null,
    ) {
    }

    /**
     * Factory method — único ponto de criação de um novo usuário.
     *
     * RN01.1: CPF único; RN01.2: Email único; RN01.8: Inicia no nível 1.
     */
    public static function register(
        Cpf $cpf,
        Email $email,
        HashedPassword $password,
        string $name,
    ): self {
        return new self(
            id: UserId::generate(),
            cpf: $cpf,
            email: $email,
            password: $password,
            name: $name,
            role: UserRole::USER,
            level: UserLevel::initial(),
            onboardingStatus: OnboardingStatus::PENDING,
            status: UserStatus::ACTIVE,
            createdAt: Instant::now(),
        );
    }

    /**
     * Conclui o onboarding do usuário.
     *
     * RN03.1: Define dados mínimos para progressão.
     * RN03.2: Ao concluir, alcança nível 2.
     * RN03.3: Pode selecionar uma ou mais modalidades.
     * RN03.4: Uma modalidade pode ser definida como principal.
     */
    /**
     * @param list<string> $modalities
     */
    public function completeOnboarding(
        UserProfile $profile,
        array $modalities,
        ?string $primaryModality,
    ): void {
        if (OnboardingStatus::COMPLETED === $this->onboardingStatus) {
            throw new OnboardingAlreadyCompletedException();
        }

        if (empty($modalities)) {
            throw new OnboardingNoModalitySelectedException();
        }

        if (null !== $primaryModality && !in_array($primaryModality, $modalities, true)) {
            throw new OnboardingInvalidPrimaryModalityException();
        }

        $this->profile = $profile;
        $this->onboardingStatus = OnboardingStatus::COMPLETED;
        $this->level = $this->level->next();
        $this->updatedAt = Instant::now();
    }

    /**
     * Suspende a conta do usuário.
     *
     * RN18.3: Conta suspensa não pode autenticar.
     * RN18.4: Registra motivo e data.
     */
    public function suspend(string $reason): void
    {
        if (UserStatus::SUSPENDED === $this->status) {
            throw new UserAlreadySuspendedException();
        }

        $this->status = UserStatus::SUSPENDED;
        $this->updatedAt = Instant::now();
    }

    /**
     * Reativa a conta do usuário.
     */
    public function reactivate(): void
    {
        if (UserStatus::ACTIVE === $this->status) {
            throw new UserAlreadyActiveException();
        }

        $this->status = UserStatus::ACTIVE;
        $this->updatedAt = Instant::now();
    }

    /**
     * Verifica se o usuário pode criar grupo.
     *
     * RN04.1: Apenas usuário a partir do nível 2 pode criar grupo.
     */
    public function canCreateGroup(): bool
    {
        return $this->level->isAtLeast(2) && $this->isActive();
    }

    public function isActive(): bool
    {
        return UserStatus::ACTIVE === $this->status;
    }

    public function isSuspended(): bool
    {
        return UserStatus::SUSPENDED === $this->status;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function cpf(): Cpf
    {
        return $this->cpf;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function profile(): ?UserProfile
    {
        return $this->profile;
    }

    public function role(): UserRole
    {
        return $this->role;
    }

    public function level(): UserLevel
    {
        return $this->level;
    }

    public function onboardingStatus(): OnboardingStatus
    {
        return $this->onboardingStatus;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?Instant
    {
        return $this->updatedAt;
    }
}
