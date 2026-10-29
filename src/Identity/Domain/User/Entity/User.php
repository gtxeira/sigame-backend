<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Entity;

use App\Identity\Domain\User\Exception\InvalidHeightException;
use App\Identity\Domain\User\Exception\InvalidWeightException;
use App\Identity\Domain\User\Exception\OnboardingAlreadyCompletedException;
use App\Identity\Domain\User\Exception\OnboardingInvalidPrimaryModalityException;
use App\Identity\Domain\User\Exception\OnboardingNoModalitySelectedException;
use App\Identity\Domain\User\Exception\UserAlreadyActiveException;
use App\Identity\Domain\User\Exception\UserAlreadySuspendedException;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\Height;
use App\Identity\Domain\User\ValueObject\OnboardingStatus;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Identity\Domain\User\ValueObject\UserLevel;
use App\Identity\Domain\User\ValueObject\UserRole;
use App\Identity\Domain\User\ValueObject\UserStatus;
use App\Identity\Domain\User\ValueObject\Weight;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Domain\ValueObject\Modality;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    /** @var Collection<int, UserModality> */
    #[ORM\OneToMany(
        targetEntity: UserModality::class,
        mappedBy: 'user',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $modalities;

    private function __construct(
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

        #[ORM\Column(name: 'height_cm', type: 'height', nullable: true)]
        private ?Height $height = null,

        #[ORM\Column(name: 'weight_kg', type: 'weight', nullable: true)]
        private ?Weight $weight = null,

        #[ORM\Column(name: 'photo_url', type: 'string', length: 2048, nullable: true)]
        private ?string $photoUrl = null,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $updatedAt = null,
    ) {
        $this->modalities = new ArrayCollection();
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
     *
     * @param list<Modality> $modalities
     */
    public function completeOnboarding(
        ?int $heightCm,
        ?float $weightKg,
        ?string $photoUrl,
        array $modalities,
        ?Modality $primaryModality,
    ): void {
        if (OnboardingStatus::COMPLETED === $this->onboardingStatus) {
            throw new OnboardingAlreadyCompletedException();
        }

        $height = null;

        if (null !== $heightCm) {
            $heightResult = Height::fromCentimeters($heightCm);

            if ($heightResult->isInvalid()) {
                throw new InvalidHeightException();
            }

            $height = $heightResult->value();
        }

        $weight = null;

        if (null !== $weightKg) {
            $weightResult = Weight::fromKilograms($weightKg);

            if ($weightResult->isInvalid()) {
                throw new InvalidWeightException();
            }

            $weight = $weightResult->value();
        }

        $modalities = self::uniqueModalities($modalities);

        if ([] === $modalities) {
            throw new OnboardingNoModalitySelectedException();
        }

        if (null !== $primaryModality && !in_array($primaryModality, $modalities, true)) {
            throw new OnboardingInvalidPrimaryModalityException();
        }

        $this->modalities->clear();

        foreach ($modalities as $modality) {
            $this->modalities->add(UserModality::create(
                user: $this,
                modality: $modality,
                isPrimary: $modality === $primaryModality,
            ));
        }

        $this->height = $height;
        $this->weight = $weight;
        $this->photoUrl = $photoUrl;
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

    public function height(): ?Height
    {
        return $this->height;
    }

    public function weight(): ?Weight
    {
        return $this->weight;
    }

    public function photoUrl(): ?string
    {
        return $this->photoUrl;
    }

    /**
     * @return list<Modality>
     */
    public function modalities(): array
    {
        $modalities = array_map(
            static fn (UserModality $userModality): Modality => $userModality->modality(),
            $this->modalities->toArray(),
        );

        usort($modalities, static fn (Modality $a, Modality $b): int => $a->value <=> $b->value);

        return $modalities;
    }

    public function primaryModality(): ?Modality
    {
        foreach ($this->modalities as $modality) {
            if ($modality->isPrimary()) {
                return $modality->modality();
            }
        }

        return null;
    }

    public function hasModality(Modality $modality): bool
    {
        return in_array($modality, $this->modalities(), true);
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

    /**
     * @param list<Modality> $modalities
     *
     * @return list<Modality>
     */
    private static function uniqueModalities(array $modalities): array
    {
        $unique = [];

        foreach ($modalities as $modality) {
            $unique[$modality->value] = $modality;
        }

        return array_values($unique);
    }
}
