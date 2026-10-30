# SIGAME — Arquitetura do Sistema

> **Status:** Proposta
> **Versão:** 0.1
> **Última atualização:** 2026-09-26
> **Autor:** Gustavo Teixeira de Sousa

---

## 1. Visão geral

O SIGAME é um **monolito modular** estruturado com **Clean Architecture**, onde cada módulo funcional possui
suas próprias camadas (Domain → Application → Infrastructure → UI). A comunicação entre módulos ocorre
exclusivamente via **Domain Events**, garantindo desacoplamento.

### 1.1 Stack tecnológica

| Camada                    | Tecnologia                                      |
|---------------------------|-------------------------------------------------|
| Runtime                   | PHP 8.5, Symfony 8.1                            |
| Web server                | FrankenPHP (Caddy)                              |
| Banco de dados            | PostgreSQL 18                                   |
| ORM                       | Doctrine ORM (via `doctrine/orm-pack`)          |
| Autenticação              | JWT — `lcobucci/jwt` + autenticador customizado |
| Armazenamento de arquivos | S3 via Flysystem (Garage localmente)            |
| Validação                 | Symfony Validator                               |
| Serialização              | Symfony Serializer                              |
| Testes                    | PestPHP (PHPUnit) + Mockery                         |
| Code style                | `friendsofphp/php-cs-fixer` (regras `@Symfony`) |
| Análise estática          | `phpstan/phpstan` + `phpstan-symfony`           |

### 1.2 Pacotes a instalar

```bash
composer require symfony/security-bundle doctrine/orm-pack
composer require symfony/serializer symfony/validator symfony/uid
composer require league/flysystem flysystem/adapter-s3
composer require lcobucci/jwt
composer require symfony/http-client
```

---

## 2. Princípios arquiteturais

1. **Monolito modular** — cada módulo é um namespace independente com suas próprias camadas. Módulos não
   importam classes concretas uns dos outros.

2. **Clean Architecture** — em cada módulo: Domain → Application → Infrastructure → Presentation (HTTP/Cli).

3. **Domain puro** — nenhuma dependência de framework nas entidades, Value Objects e regras de negócio. As
   entidades vivem em `Domain/<Agregado>/Entity/`, os VOs em `Domain/<Agregado>/ValueObject/`, e as
   políticas em `Domain/<Agregado>/Policy/`.

4. **Comunicação cross-module via Domain Events** — um módulo publica eventos, outro escuta. Nunca
   importação direta entre módulos. Eventos são disparados via `EventDispatcherInterface` (porta em
   `Shared/Application/Event/`) implementado por `SymfonyEventDispatcherAdapter`.

5. **Ports & Adapters** — repositórios e serviços externos são definidos como interfaces no Domain ou na
   Application (quando a porta representa uma capacidade da aplicação, como hashing). A Infrastructure
   fornece as implementações concretas (Doctrine, Lcobucci, Flysystem, etc.).

6. **Entidades ricas** — comportamento nas entidades, não em services anêmicas. A entidade `User` *sabe*
   quando pode criar grupo, quando está suspensa, etc.

7. **Value Objects imutáveis** — `Cpf`, `Email`, `BaseId`, `XpAmount`, `Level` validam e protegem invariantes
   no construtor. São `readonly` e comparados por valor.

### 2.1 Política de erros

- **Falha esperada e recuperável** (regra de negócio que o cliente deve tratar) → `Result` com
  `list<DomainError>`. Não usar exceção.
- **Invariante violada ou quebra de fluxo** (estado impossível, argumento inválido de VO) →
  `DomainException`, com subclasse concreta por erro.
- **Todo código de erro nasce em um catálogo** (`*ErrorCode`) do módulo:
  `SharedErrorCode`, `IdentityErrorCode`, e assim por diante. Alterar o *valor* de um caso é breaking
  change de contrato com o cliente; renomear o caso não é.
- **`new DomainError(...)` só dentro de fábricas `*Errors`** (`SharedErrors`, `IdentityErrors`). O código
  não deve ser montado no meio de um handler ou Value Object, para não divergir mensagem e `field`.
- **Todo `DomainError` carrega um `ErrorKind`** (`Validation` ou `Conflict`), derivado do catálogo de
  códigos. O `kind` não é serializado: é insumo interno para a presentation.
- **Status HTTP é derivado, nunca hardcoded no controller**: `ErrorStatusResolver` mapeia
  `Validation` → 422 e `Conflict` → 409. Quando um `Result` acumula os dois tipos, **Validation vence**
  (422), e o corpo continua trazendo todos os erros — inclusive os de conflito.
- **`DomainException` também declara `kind()`**, de modo que os dois caminhos (exceção e `Result`) usam a
  mesma regra de status.
- Códigos transversais da camada HTTP ficam em `ApiErrorCode` (`INTERNAL_ERROR`, `VALIDATION_ERROR`,
  `NOT_FOUND`) e não são erros de domínio.
- **Mensagens públicas em PT-BR**: `DomainError::$message`, `DomainException::publicMessage()` e os
  `message` de `errorDetails()` são exibidos ao cliente da API.

### 2.2 Regras de fronteira entre módulos

Estas regras definem o que pertence a cada módulo e são verificadas automaticamente pelo teste
`tests/Unit/Architecture/ModuleBoundariesTest.php`:

1. **`Shared` é o kernel, não um depósito** — não pode importar nenhum módulo funcional (`Identity`,
   `Monitoring`, futuros módulos). Tudo que é específico de um módulo fica dentro dele.
2. **Módulo funcional importa apenas a si próprio e `Shared`** — um módulo não referencia classes
   concretas de outro módulo. A comunicação é feita por Domain Events.
3. **Adapters específicos ficam no módulo dono** — Doctrine Types, listeners, controllers, DTOs,
   Policies e configurações de mapeamento de um módulo vivem sob o namespace desse módulo.
4. **Cada camada tem um dono** — a Presentation só conhece Application; a Infrastructure implementa
   portas de Domain/Application; o Domain não conhece Infrastructure nem Presentation.
5. **Domain agrupado por agregado** — dentro de cada módulo,
   `Domain/<Agregado>/{Entity,ValueObject,Repository,Event,Exception}`. Conceitos que pertencem ao
   módulo inteiro, como o catálogo de erros e a exceção base, ficam em `Domain/Error` e
   `Domain/Exception`, não dentro de um agregado.
6. **Testes espelham a origem** — `tests/Unit/<Módulo>/<Camada>/...` acompanha
   `src/<Módulo>/<Camada>/...`. Testes transversais (catálogos de erro e fronteiras) ficam em
   `tests/Unit/Architecture/`.

### 2.3 Checklist para um novo módulo

- Criar `src/<Módulo>/{Domain,Application,Infrastructure,Presentation}` conforme as camadas necessárias.
- Declarar portas em `Domain` (regras/repositórios) ou `Application` (capacidades da aplicação).
- Agrupar o Domain por agregado: `Domain/<Agregado>/{Entity,ValueObject,Repository,Event,Exception}`.
- Manter catálogo de erros e exceção base no nível do módulo (`Domain/Error` e `Domain/Exception`).
- Registrar mapeamentos Doctrine no `config/packages/doctrine.php`, apontando para a raiz
  `src/<Módulo>/Domain` do módulo.
- Publicar/consumir Domain Events com `EventDispatcherInterface`; não importar o outro módulo.
- Espelhar os testes em `tests/Unit/<Módulo>/...` e, se houver HTTP, `tests/Functional/<Módulo>/...`.
- Rodar `composer quality` (cs, phpstan e Pest) — o teste de fronteiras impede regressões.

---

## 3. Estrutura de diretórios

Cada módulo declara apenas as camadas que fazem sentido para o seu domínio. Módulos de negócio
(`Identity`, `Groups`, ...) usam Domain → Application → Infrastructure → Presentation; módulos
técnicos (como `Monitoring`) podem omitir Domain, mantendo a porta na Application e o adapter na
Infrastructure.

```
src/
├── Shared/                              # Kernel compartilhado entre módulos
│   ├── Domain/
│   │   ├── ValueObject/
│   │   │   ├── Email.php
│   │   │   ├── Cpf.php
│   │   │   ├── BaseId.php
│   │   │   ├── Modality.php
│   │   │   └── Instant.php
│   │   ├── Error/
│   │   │   ├── DomainError.php
│   │   │   ├── ErrorKind.php
│   │   │   ├── SharedErrorCode.php
│   │   │   └── SharedErrors.php
│   │   ├── Event/
│   │   │   ├── DomainEventInterface.php
│   │   │   └── AbstractDomainEvent.php
│   │   └── Exception/
│   │       ├── DomainException.php
│   │       ├── InvalidArgumentException.php
│   │       └── InvalidUuidException.php
│   ├── Application/
│   │   └── Event/
│   │       └── EventDispatcherInterface.php
│   └── Infrastructure/
│       ├── Persistence/
│       │   ├── AbstractRepository.php
│       │   └── ORM/Type/               # Tipos dos VOs realmente compartilhados
│       │       ├── BaseIdType.php
│       │       ├── CpfType.php
│       │       ├── EmailType.php
│       │       └── InstantType.php
│       └── Event/
│           └── SymfonyEventDispatcherAdapter.php
│
├── Monitoring/                          # Liveness, Readiness e saúde das dependências
│   ├── Application/
│   │   └── Service/
│   │       └── SystemHealthCheckerInterface.php   # porta da aplicação
│   ├── Infrastructure/
│   │   └── Service/
│   │       └── SystemHealthChecker.php            # adapter (Doctrine/DBAL)
│   └── Presentation/
│       └── Http/Controller/
│           └── HealthController.php
│
├── Identity/                            # Cadastro, Autenticação, Onboarding
│   ├── Domain/
│   │   ├── User/                        # agregado User
│   │   │   ├── Entity/
│   │   │   │   ├── User.php
│   │   │   │   └── UserModality.php
│   │   │   ├── ValueObject/
│   │   │   │   ├── UserId.php
│   │   │   │   ├── Credentials.php
│   │   │   │   ├── Profile.php
│   │   │   │   ├── AccessState.php
│   │   │   │   ├── UserRole.php
│   │   │   │   ├── UserLevel.php
│   │   │   │   ├── UserStatus.php
│   │   │   │   ├── HashedPassword.php
│   │   │   │   ├── Height.php
│   │   │   │   ├── Weight.php
│   │   │   │   └── OnboardingStatus.php
│   │   │   ├── Repository/
│   │   │   │   └── UserRepositoryInterface.php
│   │   │   ├── Event/
│   │   │   │   ├── UserRegistered.php
│   │   │   │   └── OnboardingCompleted.php
│   │   │   └── Exception/
│   │   │       ├── InvalidHashedPasswordException.php
│   │   │       ├── OnboardingAlreadyCompletedException.php
│   │   │       ├── OnboardingInvalidPrimaryModalityException.php
│   │   │       ├── OnboardingNoModalitySelectedException.php
│   │   │       ├── UserAlreadyActiveException.php
│   │   │       └── UserAlreadySuspendedException.php
│   │   ├── RefreshToken/                # agregado futuro
│   │   │   ├── Entity/
│   │   │   │   └── RefreshToken.php
│   │   │   └── Repository/
│   │   │       └── RefreshTokenRepositoryInterface.php
│   │   ├── Error/                       # catálogo do módulo
│   │   │   ├── IdentityErrorCode.php
│   │   │   └── IdentityErrors.php
│   │   └── Exception/                   # base do módulo
│   │       └── IdentityException.php
│   │
│   ├── Application/
│   │   ├── UseCase/
│   │   │   ├── RegisterUser/
│   │   │   │   ├── RegisterUserCommand.php
│   │   │   │   └── RegisterUserHandler.php
│   │   │   ├── AuthenticateUser/
│   │   │   │   ├── AuthenticateUserCommand.php
│   │   │   │   └── AuthenticateUserHandler.php
│   │   │   ├── RefreshToken/
│   │   │   │   ├── RefreshTokenCommand.php
│   │   │   │   └── RefreshTokenHandler.php
│   │   │   ├── Logout/
│   │   │   │   ├── LogoutCommand.php
│   │   │   │   └── LogoutHandler.php
│   │   │   └── CompleteOnboarding/
│   │   │       ├── CompleteOnboardingCommand.php
│   │   │       └── CompleteOnboardingHandler.php
│   │   └── Service/
│   │       ├── PasswordHasherInterface.php
│   │       ├── JwtTokenGeneratorInterface.php
│   │       └── RefreshTokenService.php
│   │
│   ├── Infrastructure/
│   │   ├── Persistence/
│   │   │   ├── Doctrine/
│   │   │   │   ├── Repository/
│   │   │   │   │   ├── DoctrineUserRepository.php
│   │   │   │   │   └── DoctrineRefreshTokenRepository.php
│   │   │   │   └── Migration/
│   │   │   │       └── Version*.php
│   │   │   └── ORM/
│   │   │       └── Type/               # Tipos específicos do módulo Identity
│   │   │           ├── UserIdType.php
│   │   │           ├── UserLevelType.php
│   │   │           └── HashedPasswordType.php
│   │   ├── Security/
│   │   │   ├── SymfonyPasswordHasher.php
│   │   │   └── LcobucciJwtTokenGenerator.php
│   │   └── Event/
│   │       └── UserRegisteredListener.php
│   │
│   └── Presentation/
│       └── Http/
│           ├── Controller/
│           │   ├── RegistrationController.php
│           │   ├── AuthController.php
│           │   └── OnboardingController.php
│           ├── Request/
│           │   ├── RegisterUserRequest.php
│           │   ├── LoginRequest.php
│           │   ├── RefreshTokenRequest.php
│           │   └── CompleteOnboardingRequest.php
│           └── Response/
│               └── TokenResponse.php
│
├── Groups/                              # Grupos, Membros, Supervisores
│   ├── Domain/
│   │   ├── Entity/
│   │   │   ├── Group.php
│   │   │   └── Membership.php
│   │   ├── ValueObject/
│   │   │   ├── GroupId.php
│   │   │   ├── GroupStatus.php
│   │   │   ├── MembershipStatus.php
│   │   │   └── RemovalReason.php
│   │   ├── Event/
│   │   │   ├── GroupCreated.php
│   │   │   └── MembershipApproved.php
│   │   └── Repository/
│   │       ├── GroupRepositoryInterface.php
│   │       └── MembershipRepositoryInterface.php
│   ├── Application/
│   │   └── UseCase/
│   │       ├── CreateGroup/
│   │       ├── RequestMembership/
│   │       ├── ApproveMembership/
│   │       ├── RemoveMember/
│   │       └── DesignateSupervisor/
│   ├── Infrastructure/
│   │   └── Persistence/Doctrine/
│   └── Presentation/Http/
│
├── Activities/                          # Atividades, Check-in, Presença
│   ├── Domain/
│   │   ├── Entity/
│   │   │   ├── Activity.php
│   │   │   └── Attendance.php
│   │   ├── ValueObject/
│   │   │   ├── ActivityId.php
│   │   │   ├── ActivityStatus.php
│   │   │   ├── ActivityPeriod.php
│   │   │   └── GeoPoint.php
│   │   ├── Policy/
│   │   │   └── ScheduleConflictPolicy.php
│   │   └── Repository/
│   │       ├── ActivityRepositoryInterface.php
│   │       └── AttendanceRepositoryInterface.php
│   ├── Application/
│   │   └── UseCase/
│   │       ├── CreateActivity/
│   │       ├── RescheduleActivity/
│   │       ├── CancelActivity/
│   │       ├── CheckIn/
│   │       └── ValidateAttendance/
│   ├── Infrastructure/
│   │   └── Persistence/Doctrine/
│   └── Presentation/Http/
│
├── Gamification/                        # XP, Níveis, Deleveling, Recompensas
│   ├── Domain/
│   │   ├── Entity/
│   │   │   └── UserProgress.php
│   │   ├── ValueObject/
│   │   │   ├── XpAmount.php
│   │   │   ├── Level.php
│   │   │   └── ProgressPercentage.php
│   │   ├── Policy/
│   │   │   ├── XpCalculator.php
│   │   │   ├── LevelProgression.php
│   │   │   └── DelevelingPolicy.php
│   │   └── Repository/
│   │       └── UserProgressRepositoryInterface.php
│   ├── Application/
│   │   └── UseCase/
│   │       ├── AwardXp/
│   │       └── ApplyDeleveling/
│   ├── Infrastructure/
│   │   ├── Persistence/Doctrine/
│   │   └── Console/
│   │       └── DelevelingCommand.php
│   └── Presentation/Http/
│
├── Moderation/                          # Denúncias, Bloqueio, Suspensão
│   ├── Domain/
│   │   ├── Entity/
│   │   │   ├── Report.php
│   │   │   └── UserBlock.php
│   │   └── Repository/
│   │       ├── ReportRepositoryInterface.php
│   │       └── UserBlockRepositoryInterface.php
│   ├── Application/
│   │   └── UseCase/
│   │       ├── ReportUser/
│   │       ├── BlockUser/
│   │       └── SuspendAccount/
│   ├── Infrastructure/
│   └── Presentation/Http/
│
├── Sponsorship/                         # Patrocínios, Benefícios, Vouchers
│   ├── Domain/
│   │   ├── Entity/
│   │   │   ├── Sponsor.php
│   │   │   ├── Benefit.php
│   │   │   └── Voucher.php
│   │   ├── ValueObject/
│   │   │   ├── VoucherCode.php
│   │   │   └── VoucherStatus.php
│   │   └── Repository/
│   ├── Application/
│   │   └── UseCase/
│   │       ├── CreateSponsor/
│   │       ├── ApproveBenefit/
│   │       ├── GenerateVoucher/
│   │       └── RedeemVoucher/
│   ├── Infrastructure/
│   └── Presentation/Http/
│
├── Notifications/                       # Alertas internos
│   ├── Domain/
│   │   ├── Entity/
│   │   │   └── Notification.php
│   │   ├── ValueObject/
│   │   │   ├── NotificationType.php
│   │   │   └── NotificationStatus.php
│   │   └── Repository/
│   │       └── NotificationRepositoryInterface.php
│   ├── Application/
│   │   └── UseCase/
│   │       ├── CreateNotification/
│   │       ├── MarkAsRead/
│   │       └── ListNotifications/
│   ├── Infrastructure/
│   └── Presentation/Http/
│
└── Reports/                             # Indicadores mensais
    ├── Domain/
    │   ├── Entity/
    │   │   └── MonthlyReport.php
    │   └── Repository/
    │       └── MonthlyReportRepositoryInterface.php
    ├── Application/
    │   └── UseCase/
    │       └── CalculateMonthlyReport/
    ├── Infrastructure/
    │   ├── Persistence/Doctrine/
    │   └── Console/
    │       └── CalculateReportCommand.php
    └── Presentation/Http/
```

---

## 4. Estratégia JWT — Access Token + Refresh Token com rotação

### 4.1 Ciclo de vida dos tokens

```
┌─────────────────────────────────────────────────────────────┐
│                    Fluxo de Autenticação                      │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  POST /api/v1/auth/login                                     │
│  ├─ Valida credenciais (email + senha)                       │
│  ├─ Verifica status da conta (ativa, não suspensa)           │
│  ├─ Gera access_token (15 min, stateless)                    │
│  ├─ Gera refresh_token (7 dias, persistido no DB)            │
│  ├─ Salva refresh_token (hash SHA-256) no banco              │
│  └─ Retorna: { access_token } + Set-Cookie: refresh_token    │
│                                                               │
│  GET /api/v1/* (com Authorization: Bearer <access_token>)    │
│  ├─ Validar assinatura + expiração do JWT                    │
│  ├─ Extrair user_id do payload                               │
│  └─ Autenticar e autorizar a request                         │
│                                                               │
│  POST /api/v1/auth/refresh                                   │
│  ├─ Ler refresh_token do cookie HttpOnly                     │
│  ├─ Buscar token no banco (via SHA-256 hash)                 │
│  ├─ Verificar: não expirado + não revogado                   │
│  ├─ REVOGAR o refresh_token antigo                           │
│  ├─ Gerar NOVO par (access_token + refresh_token)            │
│  ├─ Salvar novo refresh_token no banco (mesma família)       │
│  └─ Retorna: { access_token } + Set-Cookie: novo refresh     │
│                                                               │
│  POST /api/v1/auth/logout                                    │
│  ├─ Revoga TODOS os refresh_tokens do usuário                │
│  └─ Access_token expira naturalmente (15 min)                │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### 4.2 Rotação de Refresh Token com detecção de roubo

Cada refresh token pertence a uma **família** (`family_id`). Quando um token é usado:

1. O token atual é revogado
2. Um novo token é gerado na mesma família
3. Se alguém tentar reutilizar o token revogado → **toda a família é revogada**
   (indica que o token foi comprometido e pode haver um ataque de replay)

Isso garante que, se um refresh token for roubado e usado pelo atacante, o uso legítimo do
dono detectará a revogação e forçará reautenticação.

### 4.3 Armazenamento do Refresh Token

| Aspecto                | Decisão                                                             |
|------------------------|---------------------------------------------------------------------|
| Formato do token bruto | Opaque string aleatória (`rt_` + 64 caracteres hexadecimais)        |
| No banco de dados      | SHA-256 do token (nunca o valor bruto)                              |
| No client              | Cookie `HttpOnly`, `Secure`, `SameSite=Strict`, path `/api/v1/auth` |
| Expiração              | 7 dias                                                              |
| Limite por usuário     | Máximo 5 famílias ativas (device slots)                             |
| Rotação                | Cada uso gera novo token + revoga o antigo                          |

### 4.4 Conteúdo do Access Token (JWT)

```json
{
    "sub": "uuid-do-usuario",
    "iat": 1695302400,
    "exp": 1695303300,
    "type": "access"
}
```

| Campo  | Descrição                                      |
|--------|------------------------------------------------|
| `sub`  | UUID do usuário (`UserId`)                     |
| `iat`  | Timestamp de emissão (issued at)               |
| `exp`  | Timestamp de expiração (15 minutos após `iat`) |
| `type` | Tipo do token (`access`)                       |

- **Assinatura:** HMAC-SHA256 (chave via variável de ambiente `JWT_SECRET_KEY`)
- **Duração:** 15 minutos
- **Persistência:** Stateless (não persistido no banco)
- **Revogação:** Não suportada diretamente (expira naturalmente) — aceitável para V1

### 4.5 Mapper: token bruto → banco

```
Valor bruto (client):     rt_a1b2c3d4e5f6...  (64 hex chars)
SHA-256 hash (banco):     e3b0c44298fc...      (indexado, lookup)
RefreshTokenId (interno): UUID                  (gerado na criação)
```

O client armazena e envia o valor bruto. O banco armazena apenas o hash.
O `RefreshTokenId` é o identificador interno da entidade.

### 4.6 Entidade RefreshToken

```php
// src/Identity/Domain/RefreshToken/Entity/RefreshToken.php

final class RefreshToken
{
    private function __construct(
        private readonly RefreshTokenId $id,
        private readonly UserId $userId,
        private readonly string $tokenHash,
        private readonly string $familyId,
        private readonly \DateTimeImmutable $expiresAt,
        private bool $revoked = false,
        private readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {}

    public static function create(
        UserId $userId,
        string $tokenHash,
        string $familyId,
        \DateTimeImmutable $expiresAt,
    ): self;

    public function isExpired(): bool;
    public function isRevoked(): bool;
    public function revoke(): void;

    // Revoga toda a família (detecção de uso indevido)
    public function familyId(): string;
}
```

---

## 5. Módulo Identity — detalhamento

### 5.1 Entidade User (domínio rico)

```php
// src/Identity/Domain/User/Entity/User.php

final class User
{
    /** @var Collection<int, UserModality> */
    private Collection $modalities;

    private function __construct(
        private readonly UserId $id,
        private readonly Cpf $cpf,
        #[ORM\Embedded(class: Credentials::class, columnPrefix: false)]
        private Credentials $credentials,   // email + senha hasheada
        #[ORM\Embedded(class: Profile::class, columnPrefix: false)]
        private Profile $profile,           // nome, altura, peso, foto
        #[ORM\Embedded(class: AccessState::class, columnPrefix: false)]
        private AccessState $access,        // role + status
        private UserLevel $level,
        private OnboardingStatus $onboardingStatus,
        private readonly Instant $createdAt,
        private ?Instant $updatedAt = null,
    ) {}

    // Factory method — único ponto de criação de um novo usuário
    public static function register(
        Cpf $cpf,
        Email $email,
        HashedPassword $password,
        string $name,
    ): self;

    // Comportamento de negócio
    public function completeOnboarding(
        ?int $heightCm,
        ?float $weightKg,
        ?string $photoUrl,
        array $modalities,
        ?Modality $primaryModality,
    ): void;  // RN03: valida dados → concede nível 2

    public function suspend(string $reason): void;  // RN18
    public function reactivate(): void;

    // Queries de domínio (usadas por services de autorização)
    public function canCreateGroup(): bool;   // nível >= 2
    public function isActive(): bool;
    public function isSuspended(): bool;
}
```

#### 5.1.1 Separação de responsabilidades

O agregado mantém **uma única tabela** (`users`), com as responsabilidades agrupadas em
Value Objects mapeados como embeddables, sem prefixo de coluna:

| VO | Responsabilidade | Colunas |
|---|---|---|
| `Credentials` | Identificador de login e segredo | `email`, `password` |
| `Profile` | Dados de perfil e biometria | `name`, `height_cm`, `weight_kg`, `photo_url` |
| `AccessState` | Autenticação/autorização (RN18) | `role`, `status` |

Os acessores planos (`email()`, `name()`, `status()`, ...) são delegadores e mantêm o contrato
público da entidade; os VOs ficam disponíveis por `credentials()`, `profile()` e `access()` para
adapters de infraestrutura (ex.: `SecurityUser`).

**Auth não vira entidade 1:1.** Quando houver login por redes sociais, a forma correta é o
relacionamento `users` 1—N `user_identities` (`provider`, `provider_subject`, `email`,
`password` nullable), com migração de backfill `provider='local'` para os usuários existentes.
O VO `Credentials` já delimita exatamente o que será movido, então a extração não exige mexer
em `Profile`, `AccessState` nem nos casos de uso.

`level` e `onboardingStatus` permanecem na raiz por enquanto; o destino de `level` é
`Gamification/UserProgress`.

### 5.2 Comandos e Handlers

| Use Case          | Command                     | Handler                     | Responsabilidade                                                                     |
|-------------------|-----------------------------|-----------------------------|--------------------------------------------------------------------------------------|
| Cadastrar usuário | `RegisterUserCommand`       | `RegisterUserHandler`       | Valida unicidade CPF/email → hash senha → persiste → publica `UserRegistered`        |
| Autenticar        | `AuthenticateUserCommand`   | `AuthenticateUserHandler`   | Busca user → verifica senha → verifica status → gera access + refresh tokens         |
| Refresh token     | `RefreshTokenCommand`       | `RefreshTokenHandler`       | Valida refresh → revoga antigo → gera novo → retorna access + refresh                |
| Logout            | `LogoutCommand`             | `LogoutHandler`             | Revoga todos os refresh_tokens do usuário                                            |
| Onboarding        | `CompleteOnboardingCommand` | `CompleteOnboardingHandler` | Valida dados → completa onboarding → concede nível 2 → publica `OnboardingCompleted` |

### 5.3 Eventos publicados

| Evento                | Disparado quando       | Módulos que escutam                        |
|-----------------------|------------------------|--------------------------------------------|
| `UserRegistered`      | Após cadastro válido   | Gamification (cria `UserProgress` nível 1) |
| `OnboardingCompleted` | Após onboarding válido | Gamification (concede nível 2)             |

---

## 6. Comunicação entre módulos — Domain Events

### 6.1 Fluxo de eventos

```
┌──────────┐  UserRegistered    ┌───────────────┐
│ Identity │ ──────────────────→│ Gamification  │  (cria UserProgress, nível 1)
└──────────┘                    └───────────────┘

┌──────────┐  OnboardingCompleted  ┌───────────────┐
│ Identity │ ─────────────────────→│ Gamification  │  (concede nível 2)
└──────────┘                      └───────────────┘

┌────────────┐  AttendanceValidated  ┌───────────────┐
│ Activities │ ─────────────────────→│ Gamification  │  (awards XP)
└────────────┘                      └───────────────┘

┌────────────┐  ActivityCreated  ┌─────────────────┐
│ Activities │ ────────────────→│ Notifications   │  (alerta participantes)
└────────────┘                  └─────────────────┘

┌────────┐  GroupCreated     ┌─────────────────┐
│ Groups │ ─────────────────→│ Notifications   │
└────────┘                   └─────────────────┘

┌──────────────┐  LevelReached ┌──────────────┐
│ Gamification │ ─────────────→│ Sponsorship  │  (elegibilidade vouchers)
└──────────────┘               └──────────────┘
```

### 6.2 Implementação

- `DomainEventInterface` — interface base com `eventName(): string` e `occurredAt(): DateTimeImmutable`
- Cada módulo define seus eventos como classes `final` que implementam `DomainEventInterface`
- `EventDispatcherInterface` — porta definida no `Shared/Application/Event/` (usada pelos handlers)
- `SymfonyEventDispatcherAdapter` — adapta para o EventDispatcher nativo do Symfony
- Listeners registrados como serviços com `#[AsEventListener]`

---

## 7. Camada Infrastructure — Ports & Adapters

| Porta (interface no Domain)       | Adapter (Infrastructure)         | Pacote                     |
|-----------------------------------|----------------------------------|----------------------------|
| `UserRepositoryInterface`         | `DoctrineUserRepository`         | `doctrine/orm-pack`        |
| `RefreshTokenRepositoryInterface` | `DoctrineRefreshTokenRepository` | `doctrine/orm-pack`        |
| `PasswordHasherInterface`         | `SymfonyPasswordHasher`          | `symfony/security-bundle`  |
| `JwtTokenGeneratorInterface`      | `LcobucciJwtTokenGenerator`      | `lcobucci/jwt`             |
| `SystemHealthCheckerInterface`    | `SystemHealthChecker`            | `doctrine/dbal`            |
| `EventDispatcherInterface`        | `SymfonyEventDispatcherAdapter`  | `symfony/event-dispatcher` |
| `FileStorageInterface` (futuro)   | `FlysystemS3Adapter`             | `league/flysystem`         |

---

## 8. Infraestrutura Docker

### 8.1 Services necessários

| Serviço    | Imagem                 | Portas                       | Variáveis de ambiente                               |
|------------|------------------------|------------------------------|-----------------------------------------------------|
| `php`      | FrankenPHP (já existe) | 80, 443                      | `DATABASE_URL`, `JWT_SECRET_KEY`, `S3_*`            |
| `database` | `postgres:18`          | 5432                         | `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_DB` |
| `s3`       | `garagehq/garage`      | 3900 (S3 API), 3901 (Web UI) | Configuração do Garage                              |

### 8.2 Variáveis de ambiente

```env
# .env (adicionar)
DATABASE_URL="postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@database:5432/${POSTGRES_DB}?serverVersion=18&charset=utf8"
JWT_SECRET_KEY="%kernel.project_dir%/config/jwt/private.pem"
JWT_PUBLIC_KEY="%kernel.project_dir%/config/jwt/public.pem"
JWT_PASSPHRASE="trocar-em-producao"

S3_ENDPOINT="http://s3:3900"
S3_ACCESS_KEY="garage"
S3_SECRET_KEY="trocar-em-producao"
S3_BUCKET="sigame"
S3_REGION="us-east-1"
```

---

## 9. Configuração de rotas

### 9.1 Prefixo de API

Todas as rotas da API utilizam o prefixo `/api/v1`.

### 9.2 Rotas do módulo Identity

| Método | Rota                    | Controller                         | Autenticação         |
|--------|-------------------------|------------------------------------|----------------------|
| `POST` | `/api/v1/auth/register` | `RegistrationController::register` | Público              |
| `POST` | `/api/v1/auth/login`    | `AuthController::login`            | Público              |
| `POST` | `/api/v1/auth/refresh`  | `AuthController::refresh`          | Cookie refresh_token |
| `POST` | `/api/v1/auth/logout`   | `AuthController::logout`           | Autenticado          |
| `GET`  | `/api/v1/auth/me`       | `AuthController::me`               | Autenticado          |
| `PUT`  | `/api/v1/onboarding`    | `OnboardingController::complete`   | Autenticado          |

---

## 10. Configuração de serviços

A configuração é PHP puro. O carregamento é global (`App\` → `src/`) e cada classe é registrada
automaticamente por autowiring/autoconfiguração; não há service definition por módulo.

```php
// config/services.php
return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\', __DIR__.'/../src/');
};
```

O registro de mapeamentos e DBAL Types é centralizado em `config/packages/doctrine.php`, mas os
Types ficam no módulo dono do VO:

```php
use App\Identity\Infrastructure\Persistence\ORM\Type\HashedPasswordType;
use App\Identity\Infrastructure\Persistence\ORM\Type\HeightType;
use App\Identity\Infrastructure\Persistence\ORM\Type\UserIdType;
use App\Identity\Infrastructure\Persistence\ORM\Type\UserLevelType;
use App\Identity\Infrastructure\Persistence\ORM\Type\WeightType;
use App\Shared\Infrastructure\Persistence\ORM\Type\BaseIdType;
use App\Shared\Infrastructure\Persistence\ORM\Type\CpfType;
use App\Shared\Infrastructure\Persistence\ORM\Type\EmailType;
use App\Shared\Infrastructure\Persistence\ORM\Type\InstantType;

'dbal' => [
    // Necessário para o schema manager reconhecer o tipo customizado `instant`
    'mapping_types' => [
        'timestamptz' => 'instant',
    ],
    'types' => [
        // Shared
        'base_id' => BaseIdType::class,
        'email' => EmailType::class,
        'cpf' => CpfType::class,
        'instant' => InstantType::class,
        // Identity
        'user_id' => UserIdType::class,
        'hashed_password' => HashedPasswordType::class,
        'user_level' => UserLevelType::class,
        'height' => HeightType::class,
        'weight' => WeightType::class,
    ],
],
```

O `mapping_types` mapeia `timestamptz` para o tipo `instant`, permitindo que o Doctrine compare
corretamente a precisão de microssegundos das colunas de timestamp com o tipo customizado.

---

## 11. Estrutura de testes

Os testes são divididos por **tipo** — **unidade**, **funcional** e **integração** — e cada grupo
espelha a modularização do sistema: `tests/<Tipo>/<Módulo>/<Camada>/...` segue o caminho de
`src/<Módulo>/<Camada>/...`. Testes que atravessam módulos (catálogos de erro, fronteiras) ficam em
`tests/Unit/Architecture/`.

```
tests/
├── Unit/                               # grupo "unit"
│   ├── Architecture/
│   │   ├── ErrorCodeCatalogTest.php       # unicidade/classificação entre catálogos
│   │   └── ModuleBoundariesTest.php       # Shared × módulos e imports cruzados
│   ├── Identity/
│   │   ├── Domain/
│   │   │   ├── Error/IdentityErrorsTest.php
│   │   │   └── User/
│   │   │       ├── Entity/UserTest.php
│   │   │       ├── Exception/UserExceptionTest.php
│   │   │       └── ValueObject/
│   │   │           ├── UserIdTest.php
│   │   │           ├── CredentialsTest.php
│   │   │           ├── ProfileTest.php
│   │   │           ├── AccessStateTest.php
│   │   │           ├── HashedPasswordTest.php
│   │   │           ├── HeightTest.php
│   │   │           └── WeightTest.php
│   │   └── Application/
│   │       └── UseCase/RegisterUser/RegisterUserHandlerTest.php
│   ├── Gamification/
│   │   └── Domain/Policy/DelevelingPolicyTest.php
│   └── Shared/
│       ├── Domain/
│       │   ├── Error/
│       │   ├── Exception/
│       │   ├── Functional/
│       │   └── ValueObject/
│       │       ├── CpfTest.php
│       │       └── ModalityTest.php
│       └── Presentation/Http/Response/
│
├── Functional/                         # grupo "functional"
│   ├── Identity/
│   │   └── Presentation/Http/
│   │       ├── RegistrationTest.php
│   │       └── Request/RegisterUserRequestFactory.php
│   └── Monitoring/
│       └── Presentation/Http/HealthTest.php
│
├── Integration/                        # grupo "integration" (adaptadores reais, sem HTTP)
│   └── Identity/
│       └── Infrastructure/Persistence/DoctrineUserRepositoryTest.php
│
└── Helpers/                            # infraestrutura transversal de testes
    ├── ApiClient.php
    └── Factories/HasBrFakerTrait.php
```

Os grupos são declarados em `tests/Pest.php` (`pest()->group('unit')->in('Unit')`) e rodados com
`composer test:unit` / `composer test:functional` / `composer test:integration` (ou
`just test-unit` / `just test-functional` / `just test-integration`).

### Tipos de teste

| Tipo       | O que cobre                                                                    | Ferramenta                          |
|------------|--------------------------------------------------------------------------------|-------------------------------------|
| Unidade    | Entidades, VOs, policies **e casos de uso (handlers) com ports mockadas**       | PestPHP + Mockery                   |
| Funcional  | Fronteira HTTP: rotas, status code, payload, serialização, autenticação         | PestPHP + WebTestCase + BrowserKit  |
| Integração | Adaptadores reais: repositórios/banco, storage, JWT, hasher, eventos            | PestPHP + Doctrine (sem BrowserKit) |

### Isolamento do banco

Testes que tocam o banco (`integration` e `functional`) são resetados pela extensão
`Zenstruck\Foundry\PHPUnit\FoundryExtension` (registrada em `phpunit.xml.dist` com
`enabled-auto-reset=true`), que age em todo teste que estende `KernelTestCase`.
Com o `dama/doctrine-test-bundle` habilitado no env `test`, **cada teste roda dentro de uma transação
e sofre rollback ao final**, e o banco é criado/migrado **uma vez por execução**:

- `zenstruck_foundry.orm.reset.mode: migrate` faz o schema vir das migrations, não do mapping —
  migration quebrada ou dessincronizada aparece na suíte;
- o reset por teste (`doctrine:schema:drop` + `doctrine:schema:update`) deixa de acontecer;
- dados criados no teste desaparecem no rollback, então fixtures de banco vão em `beforeEach` ou no
  corpo do teste — nunca em `beforeAll`/`setUpBeforeClass`.

Como cada teste está dentro de uma transação, `CREATE DATABASE`, `TRUNCATE` e `commit()`/`rollBack()`
explícitos dentro do teste quebram o isolamento.

### Como escrever testes

1. **Mockery para as ports** — as dependências de um handler (repositórios, hashers, dispatchers)
   sempre são simuladas com `Mockery::mock(Interface::class)` em `beforeEach`, e as expectativas
   declaradas no teste: `shouldReceive(...)->once()->with(...)`, `Mockery::on(...)` para predicados
   e `shouldNotReceive(...)` para garantir que algo *não* aconteceu. Nunca `createMock()` do PHPUnit.
   O Pest fecha e verifica o Mockery automaticamente ao fim de cada teste.

2. **Sem repetir `->value()`** — quando várias asserções leem o mesmo resultado, extrair para uma
   variável local antes do `expect()`:

   ```php
   $result = ($this->handler)($command);

   expect($result->isSuccess())->toBeTrue();

   $user = $result->value();

   expect($user->cpf()->value())->toBe($cpf)
       ->and($user->email()->value())->toBe($email)
       ->and($user->name())->toBe($name);
   ```

3. **Datasets do Pest quando reduzem repetição real** — casos parametrizados (entradas inválidas,
   normalizações, payloads de rejeição) vão direto no `->with(...)`, com o dado colado ao teste:

   ```php
   it('rejects invalid cpf', function (string $input) {
       expect(Cpf::fromString($input)->isInvalid())->toBeTrue();
   })->with([
       'wrong length' => '1234567890',
       'all same digits' => '11111111111',
       'empty string' => '',
   ]);
   ```

   Manter sempre **chaves string** para nomear os casos (com chaves numéricas a saída do Pest vira
   `with dataset "0"`, `"1"`, ...). Declarar um `dataset('nome', [...])` nomeado em vez do array
   inline só quando o mesmo dataset for reutilizado por mais de um teste. Só usar dataset quando o
   cenário varia em **dados**; testos com comportamentos distintos ou setup próprio ficam literais.
   Atenção: entradas de dataset com chaves string são espalhadas como *named arguments* — para
   passar um array associativo como argumento único, aninhe-o (`'caso' => [['cpf' => '123']]`).

4. **Setup repetido vira helper** — quando a repetição é de construção (factory de entidade), não
   de dados, extrair uma função auxiliar no próprio arquivo de teste (`makeUser()`) em vez de
   dataset.

---

## 12. Ordem de implementação

### Fase 0 — Infraestrutura base

- Instalar pacotes: `doctrine/orm-pack`, `symfony/security-bundle`, `symfony/serializer`,
  `symfony/validator`, `symfony/uid`, `lcobucci/jwt`
- Configurar Docker Compose com PostgreSQL 18 + Garage (S3)
- Configurar Doctrine (conexão, migrations)
- Estruturar `src/Shared/` com VOs base (`BaseId`, `Email`, `Cpf`, `Instant`)

### Fase 1 — Identity: cadastro

- Entidade `User` com domínio rico
- `RegisterUserCommand` + `RegisterUserHandler`
- `RegistrationController`
- Migrations Doctrine
- Testes: unidade (entidade User) + integração (repositório)

### Fase 2 — Identity: autenticação JWT

- Entidade `RefreshToken`
- `LcobucciJwtTokenGenerator` (lcobucci/jwt)
- `AuthController` (login, refresh, logout, me)
- Adapter de segurança sobre o agregado: `Infrastructure/Security/SecurityUser`
  (`UserInterface` + `PasswordAuthenticatedUserInterface`), `UserProvider`
  (`loadUserByIdentifier` via `findByEmail`) e `ActiveUserChecker`
  (`checkPreAuth` → `AccessState::canAuthenticate()`, RN18.3)
- Autenticador Symfony customizado (valida access_token Bearer)
- Refresh token rotation com detecção de roubo (family_id)
- Testes: unidade (token service + adapter de segurança) + integração (HTTP: login → refresh → logout)

### Fase 3 — Identity: onboarding

- `CompleteOnboardingCommand` + `CompleteOnboardingHandler`
- `OnboardingController`
- Integração com Gamification via eventos
- Testes: fluxo completo registro → onboarding → nível 2

### Fase 4 — Groups

- Entidades `Group`, `Membership`
- Use cases: `CreateGroup`, `RequestMembership`, `ApproveMembership`,
  `RemoveMember`, `DesignateSupervisor`
- Controllers e testes de limites, estados, permissões

### Fase 5 — Activities

- Entidades `Activity`, `Attendance`
- Ciclo de vida da atividade (Agendada → Iniciada → Finalizada)
- Check-in + validação de presença
- Conflito de horário
- Testes: fluxo completo, conflitos, idempotência

### Fase 6 — Gamification

- `UserProgress`, `XpCalculator`, `DelevelingPolicy`
- Listeners: `AttendanceValidated → AwardXp`, `OnboardingCompleted → ConcedeNivel2`
- Command de deleveling (cron/scheduler)
- Testes: progressão, deleveling, idempotência

### Fase 7 — Notifications + Moderation

- Notificações internas (alertas de atividades, mudanças de grupo)
- Denúncias, bloqueio entre usuários, suspensão global

### Fase 8 — Sponsorship + Reports

- Patrocínios, benefícios, vouchers
- Indicadores mensais de desempenho dos grupos

---

## 13. Decisões pendentes (ver levantamento de requisitos)

| ID      | Questão                                                   | Impacto       | Status   |
|---------|-----------------------------------------------------------|---------------|----------|
| `DEC01` | Margem de conflito de horários e possibilidade de exceção | Atividades    | Pendente |
| `DEC02` | Tabela de XP por nível e evento                           | Gamificação   | Pendente |
| `DEC03` | Fórmula exata do deleveling                               | Gamificação   | Pendente |
| `DEC04` | Comportamento das notificações lidas/arquivadas           | Notificações  | Pendente |
| `DEC05` | Efeitos do bloqueio entre usuários                        | Moderação     | Pendente |
| `DEC06` | Matriz final de visibilidade de dados                     | Privacidade   | Pendente |
| `DEC07` | Público, distribuição e limites de vouchers               | Vouchers      | Pendente |
| `DEC08` | Fórmula e periodicidade do indicador mensal               | Indicadores   | Pendente |
| `DEC09` | Backend API (decidido: Symfony)                           | Arquitetura   | Decidido |
| `DEC10` | Janela operacional de check-in, início e finalização      | Atividades    | Pendente |
| `DEC11` | Permissões exatas do supervisor                           | Autorização   | Pendente |
| `DEC12` | Regra de atividade válida e mínimo de participantes       | Atividades/XP | Pendente |

---

## 14. Resumo das decisões arquiteturais

| Decisão                  | Escolha                                              | Justificativa                          |
|--------------------------|------------------------------------------------------|----------------------------------------|
| Mecanismo de auth        | JWT (access + refresh rotativo)                      | SPA Nuxt consome API pura, stateless   |
| Refresh token            | Opaque, SHA-256, HttpOnly cookie                     | Segurança contra XSS                   |
| Rotação de refresh       | Sim, com detecção de roubo por `family_id`           | Prevenção de replay attack             |
| Access token             | Stateless, 15 min, não persistido                    | Performance, simplicidade              |
| Persistência             | Doctrine ORM, PostgreSQL 18                          | Flexibilidade, migrations, constraints |
| Arquivos (fotos)         | S3 via Flysystem (Garage local)                      | Portabilidade, mesmo SDK em prod       |
| Comunicação cross-module | Domain Events (Symfony Dispatcher)                   | Desacoplamento total                   |
| Estrutura de diretórios  | Namespace por módulo em `src/`                       | Modular monolith claro                 |
| Camadas por módulo       | Domain → Application → Infrastructure → Presentation | Clean Architecture                     |
| Responsabilidades do User | VOs embutidos (`Credentials`, `Profile`, `AccessState`) em uma tabela | Separa auth/perfil/acesso sem quebrar a transação; split em `LoginIdentity` 1:N só quando houver login social |
| Testes                   | PestPHP + Mockery                                    | Sintaxe expressiva, datasets, mocks de ports sem boilerplate |
