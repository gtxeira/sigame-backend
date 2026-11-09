<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Entity;

use App\Identity\Domain\User\Exception\OnboardingAlreadyCompletedException;
use App\Identity\Domain\User\Exception\OnboardingInvalidPrimaryModalityException;
use App\Identity\Domain\User\Exception\OnboardingNoModalitySelectedException;
use App\Identity\Domain\User\Exception\UserCpfAlreadySetException;
use App\Identity\Domain\User\ValueObject\AccessState;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\Height;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\OnboardingStatus;
use App\Identity\Domain\User\ValueObject\Profile;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
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
use LogicException;

/**
 * Entidade User com comportamento de domínio rico.
 *
 * Representa um usuário do sistema com todas as regras de negócio
 * relacionadas a cadastro, onboarding e status da conta.
 *
 * As credenciais de login vivem em {@see UserIdentity} (1:N), o que permite
 * e-mail/senha e múltiplos provedores sociais sem duplicar o usuário. O perfil
 * ({@see Profile}) e o estado de acesso ({@see AccessState}) seguem embutidos.
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(
    name: 'uniq_users_cpf',
    fields: ['cpf'],
    options: ['where' => '(cpf IS NOT NULL)'],
)]
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

    /** @var Collection<int, UserIdentity> */
    #[ORM\OneToMany(
        targetEntity: UserIdentity::class,
        mappedBy: 'user',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $identities;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'user_id', unique: true)]
        private UserId $id,

        #[ORM\Column(type: 'email', length: 255, unique: true)]
        private Email $email,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $emailVerifiedAt,

        #[ORM\Embedded(class: Profile::class, columnPrefix: false)]
        private Profile $profile,

        #[ORM\Embedded(class: AccessState::class, columnPrefix: false)]
        private AccessState $access,

        #[ORM\Column(type: 'user_level')]
        private UserLevel $level,

        #[ORM\Column(type: 'string', length: 20, enumType: OnboardingStatus::class)]
        private OnboardingStatus $onboardingStatus,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $createdAt,

        #[ORM\Column(type: 'instant')]
        private Instant $updatedAt,

        #[ORM\Column(type: 'cpf', length: 11, nullable: true)]
        private ?Cpf $cpf = null,
    ) {
        $this->modalities = new ArrayCollection();
        $this->identities = new ArrayCollection();
    }

    /**
     * Factory method — cadastro local com e-mail e senha.
     *
     * RN01.1: CPF, quando informado, é único; é opcional no cadastro.
     * RN01.2: E-mail único.
     * RN01.8: Inicia no nível 1.
     */
    public static function register(
        ?Cpf $cpf,
        Email $email,
        HashedPassword $password,
        string $name,
    ): self {
        $now = Instant::now();

        $user = new self(
            id: UserId::generate(),
            email: $email,
            emailVerifiedAt: null,
            profile: Profile::create($name),
            access: AccessState::initial(),
            level: UserLevel::initial(),
            onboardingStatus: OnboardingStatus::PENDING,
            createdAt: $now,
            updatedAt: $now,
            cpf: $cpf,
        );

        $user->identities->add(UserIdentity::local($user, $email, $password));

        return $user;
    }

    /**
     * Factory method — cadastro via provedor social.
     *
     * Não cria senha local nem CPF. O usuário entra com onboarding pendente e
     * nível 1, como qualquer conta nova.
     */
    public static function registerWithProvider(
        IdentityProvider $provider,
        ProviderSubject $subject,
        Email $email,
        ?string $name = null,
        ?Instant $emailVerifiedAt = null,
    ): self {
        $now = Instant::now();

        $user = new self(
            id: UserId::generate(),
            email: $email,
            emailVerifiedAt: $emailVerifiedAt ?? Instant::now(),
            profile: Profile::create($name ?? self::nameFromEmail($email)),
            access: AccessState::initial(),
            level: UserLevel::initial(),
            onboardingStatus: OnboardingStatus::PENDING,
            createdAt: $now,
            updatedAt: $now,
            cpf: null,
        );

        $user->identities->add(UserIdentity::social(
            user: $user,
            provider: $provider,
            subject: $subject,
            email: $email,
            emailVerifiedAt: $emailVerifiedAt ?? Instant::now(),
        ));

        return $user;
    }

    /**
     * Vincula uma identidade social a uma conta existente.
     */
    public function linkSocialIdentity(
        IdentityProvider $provider,
        ProviderSubject $subject,
        Email $email,
        ?Instant $emailVerifiedAt = null,
    ): void {
        if ($this->hasIdentity($provider, $subject)) {
            return;
        }

        $this->identities->add(UserIdentity::social(
            user: $this,
            provider: $provider,
            subject: $subject,
            email: $email,
            emailVerifiedAt: $emailVerifiedAt ?? Instant::now(),
        ));
        $this->touch();
    }

    /**
     * Define o CPF do usuário.
     *
     * O CPF é write-once: depois de definido, só pode ser repetido (idempotência),
     * nunca alterado pelo próprio usuário.
     */
    public function assignCpf(Cpf $cpf): void
    {
        if (null !== $this->cpf) {
            if ($this->cpf->equals($cpf)) {
                return;
            }

            throw new UserCpfAlreadySetException();
        }

        $this->cpf = $cpf;
        $this->touch();
    }

    /**
     * Conclui o onboarding do usuário.
     *
     * RN03.1: Define os dados mínimos de progressão — as modalidades.
     * RN03.2: Ao concluir, alcança nível 2.
     * RN03.3: Pode selecionar uma ou mais modalidades.
     * RN03.4: Uma modalidade pode ser definida como principal.
     *
     * Altura, peso e foto não fazem parte da progressão e são atualizados
     * pelo perfil, sem reabrir o onboarding.
     *
     * @param list<Modality> $modalities
     */
    public function completeOnboarding(
        array $modalities,
        ?Modality $primaryModality,
    ): void {
        if (OnboardingStatus::COMPLETED === $this->onboardingStatus) {
            throw new OnboardingAlreadyCompletedException();
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

        $this->onboardingStatus = OnboardingStatus::COMPLETED;
        $this->level = $this->level->next();
        $this->touch();
    }

    /**
     * Substitui os dados de perfil do usuário.
     *
     * A foto é mantida porque tem ciclo de vida próprio e não é alterada por
     * este fluxo.
     */
    public function changeProfile(Profile $profile): void
    {
        $this->profile = $profile;
        $this->touch();
    }

    /**
     * Define ou remove a chave do objeto da foto de perfil.
     */
    public function changePhoto(?string $photoKey): void
    {
        $this->profile = $this->profile->withPhoto($photoKey);
        $this->touch();
    }

    /**
     * Suspende a conta do usuário.
     *
     * RN18.3: Conta suspensa não pode autenticar.
     * RN18.4: Registra motivo e data.
     *
     * O motivo ainda não é persistido; o registro dele é tratado em
     * mudança separada, junto do histórico de suspensões.
     */
    public function suspend(string $reason): void
    {
        $this->access = $this->access->suspend();
        $this->touch();
    }

    /**
     * Reativa a conta do usuário.
     */
    public function reactivate(): void
    {
        $this->access = $this->access->reactivate();
        $this->touch();
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
        return $this->access->isActive();
    }

    public function isSuspended(): bool
    {
        return $this->access->isSuspended();
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function cpf(): ?Cpf
    {
        return $this->cpf;
    }

    /**
     * @return list<UserIdentity>
     */
    public function identities(): array
    {
        return array_values($this->identities->toArray());
    }

    public function localIdentity(): ?UserIdentity
    {
        foreach ($this->identities as $identity) {
            if ($identity->isLocal()) {
                return $identity;
            }
        }

        return null;
    }

    public function hasIdentity(IdentityProvider $provider, ProviderSubject $subject): bool
    {
        foreach ($this->identities as $identity) {
            if ($identity->provider() === $provider && $identity->providerSubject()->equals($subject)) {
                return true;
            }
        }

        return false;
    }

    public function profile(): Profile
    {
        return $this->profile;
    }

    public function access(): AccessState
    {
        return $this->access;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function emailVerifiedAt(): ?Instant
    {
        return $this->emailVerifiedAt;
    }

    public function isEmailVerified(): bool
    {
        return null !== $this->emailVerifiedAt;
    }

    /**
     * Marca o e-mail canônico como verificado.
     *
     * O vínculo entre conta e endereço é idempotente: chamadas repetidas
     * preservam a data original.
     */
    public function verifyEmail(?Instant $verifiedAt = null): void
    {
        if (null !== $this->emailVerifiedAt) {
            return;
        }

        $this->emailVerifiedAt = $verifiedAt ?? Instant::now();
        $this->localIdentity()?->verifyEmail($this->emailVerifiedAt);
        $this->touch();
    }

    public function hasLocalPassword(): bool
    {
        return null !== $this->localIdentity()?->password();
    }

    public function password(): ?HashedPassword
    {
        return $this->localIdentity()?->password();
    }

    /**
     * Define ou substitui a senha local (usado no reset de senha).
     *
     * Contas criadas por provedor social passam a ter uma credencial local,
     * o que permite recuperação de acesso sem perder as identidades existentes.
     */
    public function setPassword(HashedPassword $password): void
    {
        $local = $this->localIdentity();

        if (null !== $local) {
            $local->changePassword($password);
        } else {
            $this->identities->add(UserIdentity::local($this, $this->email, $password));
        }

        $this->touch();
    }

    /**
     * Substitui a senha de uma conta que já possui credencial local.
     */
    public function changePassword(HashedPassword $password): void
    {
        $local = $this->localIdentity();

        if (null === $local) {
            throw new LogicException('A conta não possui uma identidade local com senha.');
        }

        $local->changePassword($password);
        $this->touch();
    }

    public function name(): string
    {
        return $this->profile->name();
    }

    public function height(): ?Height
    {
        return $this->profile->height();
    }

    public function weight(): ?Weight
    {
        return $this->profile->weight();
    }

    public function photoKey(): ?string
    {
        return $this->profile->photoKey();
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
        return $this->access->role();
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
        return $this->access->status();
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    /**
     * Registra a última alteração do agregado.
     *
     * O horário é mantido pelo domínio (e não por lifecycle callbacks do
     * Doctrine) para que o valor exista mesmo antes do flush.
     */
    private function touch(): void
    {
        $this->updatedAt = Instant::now();
    }

    private static function nameFromEmail(Email $email): string
    {
        $localPart = strstr($email->value(), '@', true);

        return false === $localPart || '' === $localPart ? 'Usuário' : $localPart;
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
