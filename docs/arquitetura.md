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
   entidades vivem em `Domain/Entity/`, os VOs em `Domain/ValueObject/`, e as políticas em `Domain/Policy/`.

4. **Comunicação cross-module via Domain Events** — um módulo publica eventos, outro escuta. Nunca
   importação direta entre módulos. Eventos são disparados via `DomainEventDispatcherInterface` (porta)
   implementado por `SymfonyEventDispatcherAdapter`.

5. **Ports & Adapters** — repositórios e serviços externos são definidos como interfaces no Domain. A
   Infrastructure fornece as implementações concretas (Doctrine, Lcobucci, Flysystem, etc.).

6. **Entidades ricas** — comportamento nas entidades, não em services anêmicas. A entidade `User` *sabe*
   quando pode criar grupo, quando está suspensa, etc.

7. **Value Objects imutáveis** — `Cpf`, `Email`, `BaseId`, `XpAmount`, `Level` validam e protegem invariantes
   no construtor. São `readonly` e comparados por valor.

---

## 3. Estrutura de diretórios

```
src/
├── Shared/                              # Kernel compartilhado entre módulos
│   ├── Domain/
│   │   ├── ValueObject/
│   │   │   ├── Email.php
│   │   │   ├── Cpf.php
│   │   │   ├── BaseId.php
│   │   │   └── Instant.php
│   │   ├── Event/
│   │   │   ├── DomainEventInterface.php
│   │   │   └── DomainEventDispatcherInterface.php
│   │   └── Exception/
│   │       ├── DomainException.php
│   │       └── InvalidArgumentException.php
│   ├── Application/
│   │   └── Event/
│   │       └── EventDispatcherInterface.php
│   └── Infrastructure/
│       ├── Persistence/
│       │   └── AbstractRepository.php
│       └── Event/
│           └── SymfonyEventDispatcherAdapter.php
│
├── Identity/                            # Cadastro, Autenticação, Onboarding
│   ├── Domain/
│   │   ├── Entity/
│   │   │   ├── User.php
│   │   │   └── RefreshToken.php
│   │   ├── ValueObject/
│   │   │   ├── UserId.php
│   │   │   ├── UserRole.php
│   │   │   ├── UserLevel.php
│   │   │   ├── UserStatus.php
│   │   │   ├── HashedPassword.php
│   │   │   ├── OnboardingStatus.php
│   │   │   └── UserProfile.php
│   │   ├── Event/
│   │   │   ├── UserRegistered.php
│   │   │   └── OnboardingCompleted.php
│   │   └── Repository/
│   │       ├── UserRepositoryInterface.php
│   │       └── RefreshTokenRepositoryInterface.php
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
│   │   │   │   ├── Entity/
│   │   │   │   │   ├── User.orm.php
│   │   │   │   │   └── RefreshToken.orm.php
│   │   │   │   ├── Repository/
│   │   │   │   │   ├── DoctrineUserRepository.php
│   │   │   │   │   └── DoctrineRefreshTokenRepository.php
│   │   │   │   └── Migration/
│   │   │   │       └── Version*.php
│   │   │   └── ORM/
│   │   │       └── Type/
│   │   │           ├── EmailType.php
│   │   │           ├── CpfType.php
│   │   │           └── BaseIdType.php
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
// src/Identity/Domain/Entity/RefreshToken.php

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
// src/Identity/Domain/Entity/User.php

final class User
{
    private function __construct(
        private readonly UserId $id,
        private readonly Cpf $cpf,
        private readonly Email $email,
        private readonly HashedPassword $password,
        private readonly string $name,
        private UserProfile $profile,
        private UserLevel $level,
        private OnboardingStatus $onboardingStatus,
        private UserStatus $status,
        private readonly \DateTimeImmutable $createdAt,
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
        UserProfile $profile,
        array $modalities,
        ?string $primaryModality,
    ): void;  // RN03: valida dados → concede nível 2

    public function suspend(string $reason): void;  // RN18
    public function reactivate(): void;

    // Queries de domínio (usadas por services de autorização)
    public function canCreateGroup(): bool;   // nível >= 2
    public function isActive(): bool;
    public function isSuspended(): bool;
}
```

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
- `DomainEventDispatcherInterface` — porta definida no `Shared/Domain/Event/`
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

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\:
        resource: '../src/'

    # Event listeners (módulo Identity)
    App\Identity\Infrastructure\Event\:
        resource: '../src/Identity/Infrastructure/Event/'
        tags: [ 'event_listener' ]

    # Security adapters
    App\Identity\Infrastructure\Security\:
        resource: '../src/Identity/Infrastructure/Security/'
        tags: [ 'controller.service_arguments' ]
```

---

## 11. Estrutura de testes

Os testes são divididos por **tipo** — **unidade**, **funcional** e **integração** — e cada grupo
espelha a modularização do sistema: `tests/<Tipo>/<Módulo>/<Camada>/...` segue o caminho de
`src/<Módulo>/<Camada>/...`.

```
tests/
├── Unit/                               # grupo "unit"
│   ├── Identity/
│   │   ├── Domain/
│   │   │   ├── Entity/UserTest.php
│   │   │   └── ValueObject/UserIdTest.php
│   │   └── Application/
│   │       └── UseCase/RegisterUser/RegisterUserHandlerTest.php
│   ├── Gamification/
│   │   └── Domain/Policy/DelevelingPolicyTest.php
│   └── Shared/
│       └── Domain/ValueObject/CpfTest.php
│
├── Functional/                         # grupo "functional"
│   └── Identity/
│       └── Presentation/Http/RegistrationTest.php
│
├── Integration/                        # grupo "integration" (adaptadores reais, sem HTTP)
│   └── ...                             # criado junto com o primeiro teste de infraestrutura
│
└── Helpers/
    ├── ApiClient.php
    └── Factories/
```

Os grupos são declarados em `tests/Pest.php` (`pest()->group('unit')->in('Unit')`) e rodados com
`composer test:unit` / `composer test:functional` (ou `just test-unit` / `just test-functional`).

### Tipos de teste

| Tipo       | O que cobre                                                                    | Ferramenta                          |
|------------|--------------------------------------------------------------------------------|-------------------------------------|
| Unidade    | Entidades, VOs, policies **e casos de uso (handlers) com ports mockadas**       | PestPHP + Mockery                   |
| Funcional  | Fronteira HTTP: rotas, status code, payload, serialização, autenticação         | PestPHP + WebTestCase + BrowserKit  |
| Integração | Adaptadores reais: repositórios/banco, storage, JWT, hasher, eventos            | PestPHP + Doctrine (sem BrowserKit) |

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
- Autenticador Symfony customizado (valida access_token Bearer)
- Refresh token rotation com detecção de roubo (family_id)
- Testes: unidade (token service) + integração (HTTP: login → refresh → logout)

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
| Testes                   | PestPHP + Mockery                                    | Sintaxe expressiva, datasets, mocks de ports sem boilerplate |
