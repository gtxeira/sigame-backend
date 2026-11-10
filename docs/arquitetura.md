# SIGAME — Arquitetura do Sistema

> **Status:** implementado para Identity, Groups, Activities, Gamification e Notifications; Sponsorship e Reports planejados
>
> **Versão:** 0.5
>
> **Última atualização:** 2026-11-10
>
> **Autor:** Gustavo Teixeira de Sousa

Roadmap, decisões em aberto e dívidas técnicas ficam em [implementacao.md](implementacao.md). O porquê das decisões consolidadas fica em [decisoes.md](decisoes.md).

---

## 1. Visão geral

O SIGAME é um **monolito modular** estruturado com **Clean Architecture**. Cada módulo funcional possui suas próprias camadas (`Domain → Application → Infrastructure → Presentation`). A comunicação entre módulos ocorre por **Domain Events**, sem importação direta de classes concretas.

O módulo `Shared` funciona como kernel: concentra value objects, catálogo de erros, eventos, persistência base e a infraestrutura HTTP comum. Módulos funcionais importam apenas a si próprios e `Shared`.

Hoje os módulos de negócio implementados são **Identity**, **Groups**, **Activities**, **Gamification** e **Notifications**. `Monitoring` existe como módulo técnico, sem camada de domínio. Os demais módulos estão planejados e descritos em [implementacao.md](implementacao.md#2-roadmap).

### 1.1 Stack tecnológica

| Camada | Tecnologia |
|---|---|
| Runtime | PHP 8.5 |
| Framework | Symfony 8.1 |
| Web server | FrankenPHP + Caddy |
| Banco de dados | PostgreSQL 18 |
| ORM | Doctrine ORM 3 + Doctrine Migrations 4 |
| Autenticação | Access token JWT (`lcobucci/jwt`) + refresh token opaco |
| Armazenamento de arquivos | S3 via Flysystem (Garage localmente) |
| Validação | Symfony Validator |
| Serialização | Symfony Serializer |
| Testes | PestPHP 5 + Mockery + Foundry + DAMA |
| Code style | `friendsofphp/php-cs-fixer` (regras `@Symfony`) |
| Análise estática | PHPStan 2 (nível 6) + `phpstan-symfony` |

Os pacotes principais já estão instalados e declarados em `composer.json`. A lista de comandos e a configuração de ambiente estão em [desenvolvimento.md](desenvolvimento.md) e [infraestrutura.md](infraestrutura.md).

---

## 2. Princípios arquiteturais

1. **Monolito modular** — cada módulo é um namespace independente com suas próprias camadas. Módulos não importam classes concretas uns dos outros.

2. **Clean Architecture** — em cada módulo: Domain → Application → Infrastructure → Presentation (HTTP/Cli).

3. **Domain puro** — nenhuma dependência de framework nas entidades, value objects e regras de negócio. As entidades vivem em `Domain/<Agregado>/Entity/`, os VOs em `Domain/<Agregado>/ValueObject/` e as políticas em `Domain/<Agregado>/Policy/`.

4. **Comunicação cross-module via Domain Events** — um módulo publica eventos, outro escuta. Eventos são disparados via `EventDispatcherInterface` (porta em `Shared/Application/Event/`) e implementados por `SymfonyEventDispatcherAdapter`.

5. **Ports & Adapters** — repositórios e serviços externos são definidos como interfaces no Domain ou na Application. A Infrastructure fornece as implementações concretas (Doctrine, Lcobucci, Flysystem, etc.).

6. **Entidades ricas** — comportamento nas entidades, não em services anêmicas. A entidade `User` sabe quando pode criar grupo, quando está suspensa, como concluir o onboarding, etc.

7. **Value Objects imutáveis** — `Cpf`, `Email`, `BaseId`, `XpAmount`, `Level` e similares validam e protegem invariantes no construtor. São `readonly` e comparados por valor.

### 2.1 Política de erros

- **Falha esperada e recuperável** (regra de negócio que o cliente deve tratar) → `Result` com `list<DomainError>`. Não usar exceção.
- **Invariante violada ou quebra de fluxo** (estado impossível, argumento inválido de VO) → `DomainException`, com subclasse concreta por erro.
- **Todo código de erro nasce em um catálogo** (`*ErrorCode`) do módulo: `SharedErrorCode`, `IdentityErrorCode`, e assim por diante. Alterar o *valor* de um caso é breaking change de contrato com o cliente; renomear o caso não é.
- **`new DomainError(...)` só dentro de fábricas `*Errors`** (`SharedErrors`, `IdentityErrors`). O código não deve ser montado no meio de um handler ou Value Object, para não divergir mensagem e `field`.
- **Todo `DomainError` carrega um `ErrorKind`** (`Authentication`, `Forbidden`, `Validation` ou `Conflict`), derivado do catálogo de códigos. O `kind` não é serializado: é insumo interno para a presentation.
- **Status HTTP é derivado, nunca hardcoded no controller**: `ErrorStatusResolver` mapeia `Authentication` → 401, `Forbidden` → 403, `NotFound` → 404, `Validation` → 422 e `Conflict` → 409. A precedência é `Authentication` > `Forbidden` > `NotFound` > `Validation` > `Conflict`; o corpo continua trazendo todos os erros.
- **`DomainException` também declara `kind()`**, de modo que os dois caminhos (exceção e `Result`) usam a mesma regra de status.
- **`ErrorKind` tem cinco valores**: `Authentication`, `Forbidden`, `NotFound`, `Validation` e `Conflict`. A precedência é `Authentication` > `Forbidden` > `NotFound` > `Validation` > `Conflict`.
- Códigos transversais da camada HTTP ficam em `ApiErrorCode` (`BAD_REQUEST`, `UNAUTHORIZED`, `ACCESS_TOKEN_MISSING`, `ACCESS_TOKEN_INVALID`, `ACCESS_TOKEN_EXPIRED`, `FORBIDDEN`, `ORIGIN_NOT_ALLOWED`, `NOT_FOUND`, `VALIDATION_ERROR`, `TOO_MANY_REQUESTS`, `INTERNAL_ERROR`, etc.) e não são erros de domínio.
- **Mensagens públicas em PT-BR**: `DomainError::$message`, `DomainException::publicMessage()` e os `message` de `errorDetails()` são exibidos ao cliente da API.

### 2.2 Regras de fronteira entre módulos

Estas regras são verificadas automaticamente por `tests/Unit/Architecture/ModuleBoundariesTest.php`:

1. **`Shared` é o kernel, não um depósito** — não pode importar nenhum módulo funcional (`Identity`, `Monitoring`, futuros módulos). Tudo que é específico de um módulo fica dentro dele.
2. **Módulo funcional importa apenas a si próprio, `Shared` e classes de evento de outro módulo** — entidades, repositórios, serviços, DTOs e controllers de outro módulo continuam proibidos. A comunicação é feita por Domain Events, e o `ModuleBoundariesTest` aplica a exceção do ADR-030.
3. **Adapters específicos ficam no módulo dono** — Doctrine Types, listeners, controllers, DTOs, policies e configurações de mapeamento de um módulo vivem sob o namespace desse módulo.
4. **Cada camada tem um dono** — a Presentation só conhece Application; a Infrastructure implementa portas de Domain/Application; o Domain não conhece Infrastructure nem Presentation.
5. **Domain agrupado por agregado** — dentro de cada módulo, `Domain/<Agregado>/{Entity,ValueObject,Repository,Event,Exception}`. Conceitos do módulo inteiro, como catálogo de erros e exceção base, ficam em `Domain/Error` e `Domain/Exception`.
6. **Testes espelham a origem** — `tests/Unit/<Módulo>/<Camada>/...` acompanha `src/<Módulo>/<Camada>/...`. Testes transversais ficam em `tests/Unit/Architecture/`.

### 2.3 Checklist para um novo módulo

- Criar `src/<Módulo>/{Domain,Application,Infrastructure,Presentation}` conforme as camadas necessárias.
- Declarar portas em `Domain` (regras/repositórios) ou `Application` (capacidades da aplicação).
- Agrupar o Domain por agregado: `Domain/<Agregado>/{Entity,ValueObject,Repository,Event,Exception}`.
- Manter catálogo de erros e exceção base no nível do módulo (`Domain/Error` e `Domain/Exception`).
- Registrar mapeamentos Doctrine em `config/packages/doctrine.php`, apontando para a raiz `src/<Módulo>/Domain`.
- Publicar/consumir Domain Events com `EventDispatcherInterface`; não importar o outro módulo.
- Espelhar os testes em `tests/Unit/<Módulo>/...` e, se houver HTTP, `tests/Functional/<Módulo>/...`.
- Rodar `composer quality` — o teste de fronteiras impede regressões.

---

## 3. Estrutura de diretórios

Cada módulo declara apenas as camadas que fazem sentido para o seu domínio. Módulos técnicos, como `Monitoring`, podem omitir Domain, mantendo a porta na Application e o adapter na Infrastructure.

```text
src/
├── Shared/                                  # kernel compartilhado
│   ├── Domain/
│   │   ├── ValueObject/                     # BaseId, Email, Cpf, Instant, Modality
│   │   ├── Error/                           # DomainError, ErrorKind, SharedErrorCode, SharedErrors
│   │   ├── Event/                           # DomainEventInterface, AbstractDomainEvent
│   │   ├── Exception/                       # DomainException, InvalidArgumentException, InvalidUuidException
│   │   └── Functional/                      # Result, ValidationResult
│   ├── Application/
│   │   ├── Event/                           # EventDispatcherInterface
│   │   └── Storage/                         # FileStorageInterface
│   ├── Infrastructure/
│   │   ├── Persistence/AbstractRepository.php
│   │   ├── Persistence/ORM/Type/            # BaseIdType, CpfType, EmailType, InstantType
│   │   ├── Event/SymfonyEventDispatcherAdapter.php
│   │   ├── Storage/FlysystemFileStorage.php
│   │   └── OpenApi/                         # ExampleValueResolver, FillNamedExampleValues
│   └── Presentation/Http/
│       ├── ExceptionListener/               # Api, Domain, Http e Validator
│       ├── OpenApi/                         # respostas e exemplos reutilizáveis
│       ├── RateLimit/RateLimitGuard.php
│       ├── Response/                        # ApiResponse, ResultResponder, ErrorStatusResolver, etc.
│       ├── Validation/RequestViolationFactory.php
│       └── Security/ApiAccessDeniedHandler.php
│
├── Monitoring/                              # liveness, readiness e saúde das dependências
│   ├── Application/Service/SystemHealthCheckerInterface.php
│   ├── Infrastructure/Service/SystemHealthChecker.php
│   └── Presentation/Http/Controller/HealthController.php
│
└── Identity/                                # cadastro, autenticação, identidades e refresh tokens
    ├── Domain/
    │   ├── User/
    │   │   ├── Entity/                      # User, UserIdentity, UserModality
    │   │   ├── ValueObject/                 # UserId, Profile, AccessState, HashedPassword, etc.
    │   │   ├── Repository/UserRepositoryInterface.php
    │   │   ├── Event/                       # UserRegistered, OnboardingCompleted
    │   │   └── Exception/                   # exceções de invariantes do agregado
    │   ├── RefreshToken/
    │   │   ├── Entity/RefreshToken.php
    │   │   ├── ValueObject/                 # RefreshTokenId, TokenFamilyId, TokenHash
    │   │   └── Repository/RefreshTokenRepositoryInterface.php
    │   ├── Error/                           # IdentityErrorCode, IdentityErrors
    │   └── Exception/IdentityException.php
    ├── Application/
    │   ├── DTO/                             # AccessTokenClaims, TokenPair, SocialIdentity, etc.
    │   ├── Exception/                       # InvalidTokenException, SocialAuthenticationException
    │   ├── Service/                         # portas e TokenPairIssuer, SocialIdentityProviderRegistry
    │   └── UseCase/                         # RegisterUser, AuthenticateUser, RefreshToken, Logout,
    │                                        # AuthenticateWithSocialProvider, AssignCpf
    ├── Infrastructure/
    │   ├── Persistence/Doctrine/Repository/ # DoctrineUserRepository, DoctrineRefreshTokenRepository
    │   ├── Persistence/ORM/Type/            # tipos dos VOs de Identity
    │   ├── Security/                        # hasher, geradores, authenticator, user provider, checker
    │   └── Social/                          # GoogleIdentityProvider, JwksProvider, IdTokenVerifier
    └── Presentation/Http/
        ├── Controller/                      # RegistrationController, AuthController, MeController
        ├── Request/                         # RegisterUserRequest, LoginRequest, SocialLoginRequest, AssignCpfRequest
        └── Response/                        # TokenResponse, CurrentUserResponse, RegisterUserResponse,
                                             # RefreshTokenCookieFactory
```

### 3.1 Estrutura do módulo Groups

```text
src/Groups/
├── Domain/
│   ├── Group/
│   │   ├── Entity/                        # Group, Membership
│   │   ├── ValueObject/                   # GroupId, MembershipId, MemberUserId, GroupName,
│   │   │                                  # GroupDescription, CommunicationLink, RemovalReason,
│   │   │                                  # GroupStatus, MembershipStatus, MembershipRole
│   │   ├── Policy/GroupPolicy.php         # limites de membros e de grupos por usuário
│   │   ├── Repository/                    # GroupRepositoryInterface, GroupSearchCriteria, GroupPage
│   │   └── Event/                         # GroupCreated, GroupUpdated, MembershipRequested,
│   │                                      # MembershipApproved, MembershipRejected, MemberRemoved,
│   │                                      # MembershipPromoted, MembershipDemoted,
│   │                                      # SupervisorDesignated, SupervisorRemoved, GroupActivated
│   └── Error/                             # GroupErrorCode, GroupErrors
├── Application/
│   └── UseCase/                           # CreateGroup, UpdateGroup, RequestMembership,
│                                          # ApproveMembership, RejectMembership, RemoveMember,
│                                          # ChangeMembershipRole, DesignateSupervisor,
│                                          # ClearSupervisor, ListGroups, ListMyGroups,
│                                          # GetGroup, ListMemberships
├── Infrastructure/
│   └── Persistence/
│       ├── Doctrine/Repository/           # DoctrineGroupRepository
│       └── ORM/Type/                      # tipos dos VOs de Groups
└── Presentation/Http/
    ├── Controller/                        # GroupController, GroupMembershipController,
    │                                      # GroupSupervisorController, MyGroupsController
    ├── Request/                           # CreateGroupRequest, UpdateGroupRequest, GroupListQuery,
    │                                      # RemoveMemberRequest, DesignateSupervisorRequest,
    │                                      # ChangeMembershipRoleRequest
    └── Response/                          # GroupResponse, GroupSummaryResponse,
                                           # MembershipResponse, ViewerMembershipResponse
```

### 3.2 Estrutura do módulo Activities

```text
src/Activities/
├── Domain/
│   ├── Activity/
│   │   ├── Entity/                        # Activity, Attendance, ActivityReschedule
│   │   ├── ValueObject/                   # ActivityId, ActivityWindow, ActivityStatus,
│   │   │                                  # AttendanceStatus, ActivityTitle, MeetingPoint, etc.
│   │   ├── Policy/ActivityPolicy.php      # janelas, conflito, tolerância e XP
│   │   ├── Repository/                    # ActivityRepositoryInterface, ActivitySearchCriteria, ActivityPage
│   │   └── Event/                         # ActivityCreated, ActivityFinished, AttendanceValidated, etc.
│   └── Error/                             # ActivityErrorCode, ActivityErrors
├── Application/
│   ├── Service/ActivityAccessPolicy.php
│   └── UseCase/                           # CreateActivity, UpdateActivity, CancelActivity,
│                                          # StartActivity, FinishActivity, InvalidateActivity,
│                                          # EnrollInActivity, CancelEnrollment, CheckIn,
│                                          # ValidateAttendance, ListAttendances, ListReschedules,
│                                          # ListGroupActivities, ListMyActivities, CloseExpiredActivities
├── Infrastructure/
│   ├── Persistence/Doctrine/Repository/   # DoctrineActivityRepository
│   ├── Persistence/ORM/Type/              # tipos dos VOs de Activities
│   └── Scheduler/ActivityClosingSchedule.php
└── Presentation/Http/
    ├── Controller/                        # ActivityController, ActivityLifecycleController,
    │                                      # ActivityParticipationController, MyActivitiesController
    ├── Request/                           # CreateActivityRequest, UpdateActivityRequest,
    │                                      # CancelActivityRequest, InvalidateActivityRequest, ActivityListQuery
    └── Response/                          # ActivityResponse, ActivitySummaryResponse,
                                           # AttendanceResponse, ViewerAttendanceResponse,
                                           # ActivityRescheduleResponse
```

### 3.3 Módulos em evolução e planejados

| Módulo | Estado | Responsabilidade |
|---|---|---|
| `Gamification` | Implementado | XP, níveis, ofensiva semanal, deleveling e recálculo |
| `Notifications` | Implementado | Inbox, leitura, canais e tempo real do sino |
| `Moderation` | Adiado | Denúncias e bloqueios; administração de contas fica em Identity |
| `Sponsorship` | Planejado | Patrocinadores, benefícios e vouchers |
| `Reports` | Planejado | Indicadores mensais dos grupos |

O estado de cada módulo está em [implementacao.md](implementacao.md).

### 3.4 Estrutura do módulo Gamification

```text
src/Gamification/
├── Domain/
│   ├── Progress/          # UserProgress, PlayerId, repositório e eventos
│   ├── Xp/                # XpEvent (ledger), XpKind, XpSource, XpLedger
│   ├── Rule/              # LevelCurve, ActivityXpPolicy, EngagementXpPolicy,
│   │                      # StreakPolicy, DelevelingPolicy, GamificationRules
│   ├── Streak/            # ActivityDate, WeekKey, StreakState, StreakCalculator
│   └── Error/             # GamificationErrorCode, GamificationErrors
├── Application/
│   ├── ReadModel/         # XpLedgerTotals, LedgerDivergence, LedgerAuditReport
│   ├── Service/           # ProgressLeveler, ProgressProvider, WeeklyBonusAwarder,
│   │                      # LedgerAuditor, LedgerRepairer, LedgerTotalsReader
│   └── UseCase/           # CreateProgress, GrantOnboardingXp, AwardActivityXp,
│                          # ReverseActivityXp, ApplyInactivityDecay,
│                          # GetMyProgress, ListXpHistory, RecalculateProgress
├── Infrastructure/
│   ├── Console/           # check-level-projection, audit-ledger
│   ├── Persistence/Doctrine/Reader/   # totais e integridade do ledger
│   ├── Persistence/Doctrine/Repository/
│   ├── Persistence/ORM/Type/
│   ├── Event/             # listeners de Identity e Activities
│   └── Scheduler/         # decay diário e fechamento semanal
└── Presentation/Http/
    ├── Controller/        # MyProgressController, XpHistoryController
    └── Response/
```

O XP é registrado em um ledger append-only (`xp_events`), com o saldo e o nível materializados em `user_progress`.
A fonte de verdade do nível é `user_progress.level`; `users.level` é uma projeção mantida por eventos, conforme o
[ADR-036](decisoes.md#adr-036--nível-como-projeção-de-identity).

O saldo do ledger soma **todos** os lançamentos: o estorno é um lançamento negativo e o original permanece com
`reversed_at`; filtrar os revertidos produziria um saldo incorreto. A auditoria compara esse total com
`user_progress`, recalcula o nível pela `LevelCurve` e confere a projeção. O reparo (`--repair`) usa
`UserLevelProjectionInterface` para atualizar `users.level` sem publicar eventos, pois corrige a materialização,
não concede XP.

As regras de XP e níveis estão nos
[ADR-032](decisoes.md#adr-032--modelo-de-xp-em-duas-camadas-e-ledger-append-only),
[ADR-033](decisoes.md#adr-033--curva-de-níveis-e-xp-por-atividade),
[ADR-034](decisoes.md#adr-034--ofensiva-semanal-e-bônus-de-constância) e
[ADR-035](decisoes.md#adr-035--deleveling-e-piso-no-nível-2).

---

## 4. Modelo de domínio — Identity

### 4.1 Agregado `User`

A raiz do agregado mantém perfil, acesso e nível. Credenciais de login ficam em `user_identities` (1:N), e as modalidades em `user_modalities`.

| Componente | Responsabilidade | Colunas / tabela |
|---|---|---|
| `User` | Raiz do agregado | `id`, `cpf`, `level`, `onboarding_status`, `created_at`, `updated_at` |
| `Profile` | Dados de perfil e biometria | `name`, `height_cm`, `weight_kg`, `photo_key` |
| `AccessState` | Autenticação/autorização (RN18) | `role`, `status` |
| `UserIdentity` | Login local ou social | tabela `user_identities` |
| `UserModality` | Modalidades do usuário | tabela `user_modalities` |

Comportamentos principais de `User`:

- `register()` — cadastro local com e-mail e senha; cria a identidade local.
- `registerWithProvider()` — cadastro social, sem senha e sem CPF.
- `linkSocialIdentity()` — vincula um provedor a uma conta existente.
- `assignCpf()` — define o CPF uma única vez (write-once).
- `completeOnboarding()` — valida dados e define modalidades; o nível 2 vem do XP de onboarding concedido pela gamificação.
- `suspend()` / `reactivate()` — alteram o estado de acesso.
- `canCreateGroup()` — exige nível ≥ 2 e conta ativa.

O horário de `updatedAt` é mantido pelo domínio (e não por lifecycle callback do Doctrine), para existir mesmo antes do flush.

### 4.2 `UserIdentity`

Fonte de verdade para autenticação:

- `provider = local`: `provider_subject` é o e-mail normalizado e `password` é obrigatória;
- `provider = google`: `provider_subject` é o `sub` do provedor e `password` é `null`;
- o par `(provider, provider_subject)` é único;
- o vínculo automático por e-mail só ocorre quando o provedor afirma `email_verified=true`;
- `User::email()` e `User::password()` delegam para a identidade local (ou para o primeiro e-mail social, quando não existe identidade local).

### 4.3 `UserModality`

- Chave primária composta `(user_id, modality)`.
- Índice único parcial garante no máximo uma modalidade principal por usuário.
- `completeOnboarding()` deduplica as modalidades antes de persistir; a leitura ordena por valor.

### 4.4 `RefreshToken`

- Só guarda o hash SHA-256 do token bruto.
- Pertence a uma família (`family_id`) e aponta para o substituto (`replaced_by_id`).
- `revoke()`, `isExpiredAt()`, `wasRevokedWithin()` e `belongsToFamily()` concentram o comportamento.
- O ciclo de vida e as regras de rotação estão em [autenticacao.md](autenticacao.md).

### 4.5 CPF opcional e write-once

O CPF é opcional no cadastro local e social. Quando informado, é único (índice parcial `WHERE cpf IS NOT NULL`). Depois de definido, o próprio usuário não pode alterá-lo; correções exigem fluxo administrativo futuro. O CPF é exigido apenas para resgatar recompensas/vouchers externos.

A decisão completa está em [decisoes.md](decisoes.md#adr-005--cpf-opcional-e-write-once).

### 4.6 Agregado `Group` (módulo Groups)

A raiz do agregado mantém os dados do grupo, o criador e as participações. O criador é registrado em `created_by`, independente do papel de administrador, e o grupo pode ter vários admins.

| Componente | Responsabilidade | Colunas / tabela |
|---|---|---|
| `Group` | Raiz do agregado | `id`, `created_by`, `name`, `description`, `modality`, `communication_link`, `created_at`, `updated_at` |
| `Membership` | Episódio de participação | tabela `group_memberships` |

Comportamentos principais de `Group`:

- `create()` — cria o grupo e registra o criador como primeiro administrador.
- `requestMembership()` — cria um episódio `PENDING`, recusando duplicidade ativa.
- `approveMembership()` — exige admin, respeita 5 membros e 3 grupos por usuário.
- `rejectMembership()` — encerra um episódio `PENDING` como `REJECTED`.
- `removeMembership()` — registra autor, data e motivo; impede remover o último admin.
- `promoteToAdmin()` / `demoteToMember()` — gerenciam múltiplos admins; o último admin é protegido.
- `designateSupervisor()` / `clearSupervisor()` — supervisor opcional, sempre membro aprovado.
- `changeDetails()` — atualiza nome, descrição, modalidade e link.
- `status()` — deriva `FORMING`, `ACTIVE` ou `FULL` da contagem de aprovados.

Regras:

- `GroupPolicy` fixa 3 aprovados para ativar, 5 para lotar e 3 grupos por usuário.
- `Membership` é append-only: rejeição e remoção preservam o episódio; reentrada cria outro.
- Índices parciais garantem uma única participação ativa por par grupo/usuário e um único supervisor aprovado.
- O `communicationLink` só é retornado para membro aprovado.
- A leitura de nível do usuário usa a porta `UserLevelReaderInterface` do `Shared` (ADR-020).

### 4.7 Agregado `Activity` (módulo Activities)

A raiz do agregado mantém a janela planejada, o ciclo de vida, as inscrições e o histórico de remarcações.

| Componente | Responsabilidade | Colunas / tabela |
|---|---|---|
| `Activity` | Raiz do agregado | `id`, `group_id`, `created_by`, `title`, `description`, `starts_at`, `ends_at`, `estimated_duration_minutes`, `meeting_point`, `route`, `estimated_distance_meters`, `status`, timestamps de ciclo de vida, `auto_close_at`, flags automáticas |
| `Attendance` | Episódio de inscrição/presença | tabela `activity_attendances` |
| `ActivityReschedule` | Histórico append-only de remarcação | tabela `activity_reschedules` |

Comportamentos principais de `Activity`:

- `create()` — cria a atividade agendada.
- `updateDetails()` / `reschedule()` — alteram dados ou janela, com histórico.
- `cancel()` / `start()` / `finish()` / `invalidate()` — ciclo de vida com janelas operacionais.
- `enroll()` / `cancelEnrollment()` / `checkIn()` / `validateAttendance()` — inscrição e presença idempotentes.
- `finishAutomatically()` / `cancelAutomatically()` — fechamento pelo scheduler.
- `isXpEligible()` — exige `FINISHED` e pelo menos 2 check-ins.

Regras:

- `ActivityPolicy` centraliza janelas, conflito, tolerância e mínimo de XP.
- O conflito é verificado na inscrição e na remarcação, com lock transacional.
- O scheduler roda a cada 5 minutos e fecha/cancela atividades vencidas.
- A autorização sobre o grupo usa `GroupAccessReaderInterface` do `Shared` (ADR-029).

---

## 5. Casos de uso e eventos

### 5.1 Casos de uso implementados

| Use case | Command | Handler | Responsabilidade |
|---|---|---|---|
| Cadastrar usuário | `RegisterUserCommand` | `RegisterUserHandler` | Valida unicidade CPF/e-mail → hash senha → persiste → publica `UserRegistered`; o listener enfileira a verificação |
| Autenticar | `AuthenticateUserCommand` | `AuthenticateUserHandler` | Busca identidade local → verifica senha e status → emite tokens |
| Login social | `AuthenticateWithSocialProviderCommand` | `AuthenticateWithSocialProviderHandler` | Troca code → valida id_token → cria/vincula identidade → emite tokens |
| Refresh token | `RefreshTokenCommand` | `RefreshTokenHandler` | Valida refresh opaco → detecta reuso → rotaciona o par |
| Logout | `LogoutCommand` | `LogoutHandler` | Revoga a família do cookie ou todas as famílias |
| Definir CPF | `AssignCpfCommand` | `AssignCpfHandler` | Valida CPF → verifica unicidade → define (write-once) |
| Concluir onboarding | `CompleteOnboardingCommand` | `CompleteOnboardingHandler` | Carrega o usuário → conclui no agregado → persiste → publica `OnboardingCompleted` |
| Criar grupo | `CreateGroupCommand` | `CreateGroupHandler` | Valida nível 2 e limite de 3 grupos → cria o grupo com o criador admin → publica `GroupCreated` |
| Atualizar grupo | `UpdateGroupCommand` | `UpdateGroupHandler` | Exige admin → aplica JSON Merge Patch → publica `GroupUpdated` |
| Solicitar entrada | `RequestMembershipCommand` | `RequestMembershipHandler` | Cria episódio `PENDING`, recusando duplicidade → publica `MembershipRequested` |
| Aprovar solicitação | `ApproveMembershipCommand` | `ApproveMembershipHandler` | Exige admin → valida 5 membros e 3 grupos → publica `MembershipApproved` e, se ativou, `GroupActivated` |
| Rejeitar solicitação | `RejectMembershipCommand` | `RejectMembershipHandler` | Exige admin → encerra `PENDING` como `REJECTED` → publica `MembershipRejected` |
| Remover membro | `RemoveMemberCommand` | `RemoveMemberHandler` | Exige admin, motivo e não remove o último admin → publica `MemberRemoved` |
| Alterar papel | `ChangeMembershipRoleCommand` | `ChangeMembershipRoleHandler` | Promove/rebaixa membro aprovado, protegendo o último admin → publica `MembershipPromoted`/`MembershipDemoted` |
| Designar supervisor | `DesignateSupervisorCommand` | `DesignateSupervisorHandler` | Exige admin e membro aprovado → publica `SupervisorDesignated` |
| Remover supervisor | `ClearSupervisorCommand` | `ClearSupervisorHandler` | Idempotente → publica `SupervisorRemoved` quando havia supervisor |
| Listar grupos | `ListGroupsQuery` | `ListGroupsHandler` | Busca paginada com filtros de modalidade, estado, nome e disponibilidade |
| Listar meus grupos | `ListMyGroupsQuery` | `ListMyGroupsHandler` | Lista grupos com participação aprovada do usuário |
| Obter grupo | `GetGroupQuery` | `GetGroupHandler` | Carrega o grupo; o link só é exposto na resposta para membro aprovado |
| Listar membros | `ListMembershipsQuery` | `ListMembershipsHandler` | Exige admin → lista pendentes, aprovados e histórico |
| Criar atividade | `CreateActivityCommand` | `CreateActivityHandler` | Exige admin → valida grupo ativo → cria e publica `ActivityCreated` |
| Atualizar/remarcar | `UpdateActivityCommand` | `UpdateActivityHandler` | Exige admin → atualiza dados/janela, grava histórico e publica eventos |
| Cancelar | `CancelActivityCommand` | `CancelActivityHandler` | Exige admin → só `SCHEDULED` → publica `ActivityCancelled` |
| Iniciar | `StartActivityCommand` | `StartActivityHandler` | Admin/supervisor → valida janela → publica `ActivityStarted` |
| Finalizar | `FinishActivityCommand` | `FinishActivityHandler` | Admin/supervisor → valida janela → publica `ActivityFinished` |
| Invalidar | `InvalidateActivityCommand` | `InvalidateActivityHandler` | Exige admin e motivo → publica `ActivityInvalidated` |
| Inscrever-se | `EnrollInActivityCommand` | `EnrollInActivityHandler` | Membro aprovado → valida conflito → cria episódio |
| Cancelar inscrição | `CancelEnrollmentCommand` | `CancelEnrollmentHandler` | Próprio inscrito → idempotente |
| Check-in | `CheckInCommand` | `CheckInHandler` | Próprio inscrito → valida janela → publica `AttendanceCheckedIn` |
| Validar presença | `ValidateAttendanceCommand` | `ValidateAttendanceHandler` | Admin/supervisor → exige check-in e janela → publica `AttendanceValidated` |
| Listar presenças | `ListAttendancesQuery` | `ListAttendancesHandler` | Admin/supervisor lista episódios |
| Listar atividades | `ListGroupActivitiesQuery` | `ListGroupActivitiesHandler` | Membro aprovado lista com filtros |
| Listar minhas atividades | `ListMyActivitiesQuery` | `ListMyActivitiesHandler` | Lista atividades com inscrição ativa |
| Fechar expiradas | `CloseExpiredActivitiesMessage` | `CloseExpiredActivitiesHandler` | Scheduler cancela não iniciadas e finaliza iniciadas vencidas |

A camada HTTP do onboarding expõe `POST /api/v1/users/me/onboarding`. O estado está em [implementacao.md](implementacao.md).

### 5.2 Eventos

| Evento | Disparado quando | Consumidores |
|---|---|---|
| `UserRegistered` | Após cadastro local ou social | `CreateProgressOnUserRegistered` cria `UserProgress` |
| `OnboardingCompleted` | Após onboarding válido | `GrantOnboardingXpOnOnboardingCompleted` concede 100 XP e nível 2 |
| `GroupCreated` | Após criar um grupo | Nenhum (não notificado na V1) |
| `GroupUpdated` | Após atualizar dados do grupo | Nenhum (não notificado na V1) |
| `MembershipRequested` | Após solicitação pendente | Notifications (listener do catálogo) |
| `MembershipApproved` | Após aprovar solicitação | Notifications (listener do catálogo) |
| `MembershipRejected` | Após rejeitar solicitação | Notifications (listener do catálogo) |
| `MemberRemoved` | Após remover membro | Notifications (listener do catálogo) |
| `MembershipPromoted` | Após promover membro a admin | Notifications (listener do catálogo) |
| `MembershipDemoted` | Após rebaixar admin | Notifications (listener do catálogo) |
| `SupervisorDesignated` | Após designar supervisor | Notifications (listener do catálogo) |
| `SupervisorRemoved` | Após remover designação | Notifications (listener do catálogo) |
| `GroupActivated` | Ao atingir 3 membros aprovados | Notifications (grupo ativo) |
| `ActivityCreated` | Após criar atividade | Notifications (listener do catálogo) |
| `ActivityUpdated` | Após alterar dados da atividade | Notifications (listener do catálogo) |
| `ActivityRescheduled` | Após remarcar atividade | Notifications (listener do catálogo) |
| `ActivityCancelled` | Após cancelamento manual ou automático | Notifications (listener do catálogo) |
| `ActivityStarted` | Após iniciar atividade | Nenhum (não notificado na V1) |
| `ActivityFinished` | Após finalização manual ou automática | Notifications (listener do catálogo) |
| `ActivityInvalidated` | Após invalidar atividade | `ReverseActivityXpOnActivityInvalidated` estorna o XP; Notifications |
| `ActivityEnrollmentCreated` | Após inscrição | Nenhum (não notificado na V1) |
| `ActivityEnrollmentCancelled` | Após cancelar inscrição | Nenhum (não notificado na V1) |
| `AttendanceCheckedIn` | Após check-in | Nenhum (não notificado na V1) |
| `AttendanceValidated` | Após validar presença | `AwardActivityXpOnAttendanceValidated` concede XP; Notifications |
| `XpAwarded` | Após conceder ou estornar XP | Nenhum (não notificado na V1) |
| `LevelReached` | Ao subir de nível | `SyncUserLevelOnLevelChanged` projeta `users.level`; Notifications |
| `LevelLost` | Ao cair de nível | `SyncUserLevelOnLevelChanged` projeta `users.level`; Notifications |
| `WeeklyGoalCompleted` | Ao completar 2 atividades na semana | Notifications (level/meta) |
| `StreakUpdated` | Ao manter, completar ou perder a ofensiva semanal | Nenhum (não notificado na V1) |
| `UserSuspended` | Após suspender uma conta | Notifications (e-mail de conta) |
| `UserReactivated` | Após reativar uma conta | Notifications (e-mail e sino) |

### 5.3 Fluxos planejados

```text
Identity  --UserRegistered--------> Gamification   (cria UserProgress, nível 1)
Identity  --OnboardingCompleted---> Gamification   (+100 XP; nível 2)
Identity  --UserSuspended---------> Notifications  (e-mail de conta)
Identity  --UserReactivated-------> Notifications  (e-mail e sino)
Activities --AttendanceValidated--> Gamification   (XP de atividade e bônus semanal)
Activities --ActivityInvalidated--> Gamification   (estorno de XP)
Activities --AttendanceValidated--> Notifications  (presença validada)
Activities --ActivityCreated------> Notifications  (membros aprovados)
Activities --ActivityRescheduled--> Notifications  (inscritos)
Activities --ActivityCancelled----> Notifications  (inscritos)
Activities --ActivityInvalidated--> Notifications  (inscritos)
Activities --ActivityFinished-----> Notifications  (admins/supervisor)
Groups    --MembershipRequested---> Notifications  (admins)
Groups    --MembershipApproved----> Notifications  (solicitante)
Groups    --MembershipRejected----> Notifications  (solicitante)
Groups    --MemberRemoved---------> Notifications  (removido)
Groups    --GroupActivated--------> Notifications  (membros aprovados)
Gamification --LevelReached-------> Identity       (projeta users.level)
Gamification --LevelLost----------> Identity       (projeta users.level)
Gamification --LevelReached-------> Notifications
Gamification --LevelLost----------> Notifications
Gamification --WeeklyGoalCompleted--> Notifications
Gamification --LevelReached-------> Sponsorship    (elegibilidade de vouchers)
```

### 5.4 Implementação

- `DomainEventInterface` — interface base com `eventName(): string` e `occurredAt(): DateTimeImmutable`.
- Cada módulo define seus eventos como classes `final` que implementam `DomainEventInterface`.
- `EventDispatcherInterface` — porta em `Shared/Application/Event/`, usada pelos handlers.
- `SymfonyEventDispatcherAdapter` — adapta para o EventDispatcher nativo do Symfony.
- Listeners são registrados como serviços com `#[AsEventListener]`.

---

## 6. Camada Infrastructure — Ports & Adapters

| Porta | Adapter | Pacote |
|---|---|---|
| `UserRepositoryInterface` | `DoctrineUserRepository` | `doctrine/orm` |
| `RefreshTokenRepositoryInterface` | `DoctrineRefreshTokenRepository` | `doctrine/orm` |
| `PasswordHasherInterface` | `SymfonyPasswordHasher` | `symfony/security-bundle` |
| `AccessTokenGeneratorInterface` | `LcobucciAccessTokenGenerator` | `lcobucci/jwt` |
| `RefreshTokenGeneratorInterface` | `OpaqueRefreshTokenGenerator` | `random_bytes` + SHA-256 |
| `SocialIdentityProviderInterface` | `GoogleIdentityProvider` | `symfony/http-client` + JWKS |
| `SystemHealthCheckerInterface` | `SystemHealthChecker` | `doctrine/dbal` |
| `EventDispatcherInterface` | `SymfonyEventDispatcherAdapter` | `symfony/event-dispatcher` |
| `IdentityMailDispatcherInterface` | `MessengerIdentityMailDispatcher` | `symfony/messenger` + `symfony/doctrine-messenger` |
| `IdentityMailerInterface` | `SymfonyIdentityMailer` | `symfony/mailer` |
| `FileStorageInterface` | `FlysystemFileStorage` | `league/flysystem` + `aws/aws-sdk-php` |
| `ProfilePhotoStorageInterface` | `S3ProfilePhotoStorage` | `league/flysystem` + `symfony/uid` |
| `UserLevelReaderInterface` | `DoctrineUserLevelReader` | `doctrine/orm` (adapter em Identity) |
| `GroupRepositoryInterface` | `DoctrineGroupRepository` | `doctrine/orm` |
| `GroupAccessReaderInterface` | `DoctrineGroupAccessReader` | `doctrine/orm` (adapter em Groups) |
| `GroupModalityReaderInterface` | `DoctrineGroupModalityReader` | `doctrine/orm` (adapter em Groups) |
| `ActivityRepositoryInterface` | `DoctrineActivityRepository` | `doctrine/orm` |
| `ActivityXpReaderInterface` | `DoctrineActivityXpReader` | `doctrine/orm` (adapter em Activities) |
| `UserActiveReaderInterface` | `DoctrineUserContactReader` | `doctrine/orm` (adapter em Identity) |
| `UserContactReaderInterface` | `DoctrineUserContactReader` | `doctrine/orm` (adapter em Identity) |
| `GroupSummaryReaderInterface` | `DoctrineGroupSummaryReader` | `doctrine/orm` (adapter em Groups) |
| `ActivitySummaryReaderInterface` | `DoctrineActivitySummaryReader` | `doctrine/orm` (adapter em Activities) |
| `RealtimePublisherInterface` | `MercureRealtimePublisher` | `symfony/mercure` |
| `StreamAuthorizationProviderInterface` | `MercureStreamAuthorizationProvider` | `symfony/mercure` + `lcobucci/jwt` |
| `NotificationChannelInterface` | `EmailNotificationChannel` | `symfony/mailer` |

O storage usa o mesmo contrato S3 em desenvolvimento (Garage) e produção. A decisão de upload e o formato da chave estão no [ADR-016](decisoes.md#adr-016--upload-de-foto-de-perfil-com-chave-em-s3); a escolha do adapter está no [ADR-010](decisoes.md#adr-010--arquivos-em-s3-via-flysystemgarage).

A porta `FileStorageInterface` vive em `Shared/Application/Storage`, porque é infraestrutura genérica. `ProfilePhotoStorageInterface` fica em `Identity/Application/Storage` e traduz o caso de uso para chave/URL. Em teste, o adapter é local (`var/storage-test`), sem depender do Garage.

### 6.1 E-mail transacional assíncrono

O request continua criando e persistindo o token (apenas o hash), mas não envia mais o e-mail. A porta `IdentityMailDispatcherInterface` publica uma mensagem explícita no Messenger:

```text
Request -> token + hash no banco
        -> MessengerIdentityMailDispatcher
        -> SendEmailVerificationMessage / SendPasswordResetMessage
        -> transport async (Doctrine)
        -> worker -> IdentityEmailMessageDecoder -> IdentityMailerInterface
```

- O token bruto vai no payload cifrado com `PayloadEncryptor` (libsodium `secretbox`); o `tokenHash` fica em claro para a compensação.
- O transporte usa `messenger_messages` e `failure_transport`; a decisão completa está no [ADR-014](decisoes.md#adr-014--e-mails-de-identidade-assíncronos-com-payload-cifrado).
- Retries reutilizam o mesmo token. Em falha definitiva, o listener consome o token de reset e mantém o de verificação válido para reenvio.
- O envio real acontece no worker; `framework.mailer.message_bus` fica desativado para não enfileirar duas vezes.

---

### 6.2 Schedulers de atividades e gamificação

O módulo Activities usa `symfony/scheduler` para executar `CloseExpiredActivitiesMessage` a cada 5 minutos:

```text
scheduler_activities
  -> CloseExpiredActivitiesHandler
  -> findScheduledToAutoCancel / findStartedToAutoFinish
  -> finishAutomatically / cancelAutomatically
  -> ActivityFinished / ActivityCancelled (automatic=true)
```

O módulo Gamification usa o mesmo componente para executar `ApplyInactivityDecayMessage` uma vez por dia:

```text
scheduler_gamification
  -> ApplyInactivityDecayHandler
  -> findDueForDecay
  -> DelevelingPolicy
  -> InactivityDecay events + XpDecayed + LevelLost
```

Os transportes `scheduler_activities` e `scheduler_gamification` são consumidos pelo serviço `scheduler` do Compose.
Em produção, esse serviço precisa estar sempre ativo; se ele parar, o fechamento automático e o deleveling atrasam
até a próxima execução.

---

### 6.3 Notificações e Mercure

O módulo `Notifications` persiste o inbox e publica atualizações em um tópico privado por usuário:

```text
Domain Event -> listener -> CreateNotificationsMessage -> worker
  -> resolve público/conteúdo -> notifications + notification_deliveries
  -> Mercure (notification.created / notification.read / notifications.read_all)
  -> entrega externa (e-mail)
```

- O canal interno é o próprio registro persistido; Mercure é apenas transporte SSE.
- `GET /api/v1/notifications/realtime` devolve `subscribeUrl`, token de curta duração e expiração.
- O token concede apenas `subscribe` no tópico `.../notifications/{userId}`; publicação é exclusiva do servidor.
- O frontend usa `Authorization: Bearer` no SSE (fetch-event-source ou equivalente), sem token na URL.
- A publicação é best-effort: falha do hub não desfaz a notificação e a API continua sendo a fonte de verdade.
- E-mail é usado apenas para suspensão/reativação de conta; WhatsApp e push ficam como extensões.

O hub do Caddy usa protocolo legado 0.x (`protocol_version_compatibility 8`). O bundle está configurado com
`ProtocolVersion::Legacy`; a migração para 1.0 deve ser feita junto com a atualização do hub.

---

## 7. Configuração e rotas

### 7.1 Serviços

A configuração é PHP puro. O carregamento é global (`App\` → `src/`) e cada classe é registrada automaticamente por autowiring/autoconfiguração; não há service definition por módulo.

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

O registro de mapeamentos e DBAL Types é centralizado em `config/packages/doctrine.php`, mas os Types ficam no módulo dono do VO. O `mapping_types` mapeia `timestamptz` para o tipo `instant`, permitindo que o Doctrine compare corretamente a precisão de microssegundos.

A decisão sobre configuração em PHP está em [decisoes.md](decisoes.md#adr-008--configuração-em-php-com-autowiring).

### 7.2 Rotas

As rotas de negócio usam o prefixo `/api/v1`, declarado na classe do controller. Os probes operacionais ficam em `/api/health`, sem versionamento, e a documentação em `/doc` e `/doc.json`.

| Método | Rota | Controller | Autenticação |
|---|---|---|---|
| `POST` | `/api/v1/auth/register` | `RegistrationController::register` | Pública |
| `POST` | `/api/v1/auth/login` | `AuthController::login` | Pública |
| `POST` | `/api/v1/auth/social/{provider}/start` | `SocialAuthController::start` | Pública (Bearer opcional) |
| `POST` | `/api/v1/auth/social/{provider}/callback` | `SocialAuthController::callback` | Pública + cookie `social_flow` |
| `POST` | `/api/v1/auth/social/{provider}/link` | `SocialAuthController::link` | Bearer JWT |
| `POST` | `/api/v1/auth/email/verify` | `EmailVerificationController::verify` | Pública |
| `POST` | `/api/v1/auth/email/verification/resend` | `EmailVerificationController::resend` | Bearer JWT |
| `POST` | `/api/v1/auth/password/forgot` | `PasswordController::forgot` | Pública |
| `POST` | `/api/v1/auth/password/reset` | `PasswordController::reset` | Pública |
| `POST` | `/api/v1/auth/refresh` | `AuthController::refresh` | Cookie `refresh_token` |
| `POST` | `/api/v1/auth/logout` | `AuthController::logout` | Cookie `refresh_token` |
| `POST` | `/api/v1/auth/logout/all` | `AuthController::logoutAll` | Bearer JWT |
| `GET` | `/api/v1/users/me` | `MeController::me` | Bearer JWT |
| `PATCH` | `/api/v1/users/me/cpf` | `MeController::assignCpf` | Bearer JWT |
| `POST` | `/api/v1/users/me/onboarding` | `OnboardingController::complete` | Bearer JWT |
| `POST` | `/api/v1/users/me/password` | `MePasswordController::change` | Bearer JWT |
| `POST` | `/api/v1/users/me/identities/{provider}` | `MeIdentityController::link` | Bearer JWT |
| `POST` | `/api/v1/groups` | `GroupController::create` | Bearer JWT |
| `GET` | `/api/v1/groups` | `GroupController::list` | Bearer JWT |
| `GET` | `/api/v1/users/me/groups` | `MyGroupsController::list` | Bearer JWT |
| `GET` | `/api/v1/groups/{id}` | `GroupController::show` | Bearer JWT |
| `PATCH` | `/api/v1/groups/{id}` | `GroupController::update` | Bearer JWT + admin |
| `POST` | `/api/v1/groups/{id}/membership-requests` | `GroupMembershipController::request` | Bearer JWT |
| `GET` | `/api/v1/groups/{id}/memberships` | `GroupMembershipController::list` | Bearer JWT + admin |
| `POST` | `/api/v1/groups/{id}/memberships/{membershipId}/approve` | `GroupMembershipController::approve` | Bearer JWT + admin |
| `POST` | `/api/v1/groups/{id}/memberships/{membershipId}/reject` | `GroupMembershipController::reject` | Bearer JWT + admin |
| `POST` | `/api/v1/groups/{id}/memberships/{membershipId}/remove` | `GroupMembershipController::remove` | Bearer JWT + admin |
| `PATCH` | `/api/v1/groups/{id}/memberships/{membershipId}/role` | `GroupMembershipController::changeRole` | Bearer JWT + admin |
| `PUT` | `/api/v1/groups/{id}/supervisor` | `GroupSupervisorController::designate` | Bearer JWT + admin |
| `DELETE` | `/api/v1/groups/{id}/supervisor` | `GroupSupervisorController::clear` | Bearer JWT + admin |
| `POST` | `/api/v1/groups/{groupId}/activities` | `ActivityController::create` | Bearer JWT + admin/admin SIGAME |
| `GET` | `/api/v1/groups/{groupId}/activities` | `ActivityController::listGroup` | Bearer JWT + membro aprovado |
| `GET` | `/api/v1/activities/{id}` | `ActivityController::show` | Bearer JWT + membro aprovado |
| `PATCH` | `/api/v1/activities/{id}` | `ActivityController::update` | Bearer JWT + admin/admin SIGAME |
| `GET` | `/api/v1/activities/{id}/reschedules` | `ActivityController::listReschedules` | Bearer JWT + admin/admin SIGAME |
| `POST` | `/api/v1/activities/{id}/cancel` | `ActivityLifecycleController::cancel` | Bearer JWT + admin/admin SIGAME |
| `POST` | `/api/v1/activities/{id}/start` | `ActivityLifecycleController::start` | Bearer JWT + admin/supervisor/admin SIGAME |
| `POST` | `/api/v1/activities/{id}/finish` | `ActivityLifecycleController::finish` | Bearer JWT + admin/supervisor/admin SIGAME |
| `POST` | `/api/v1/activities/{id}/invalidate` | `ActivityLifecycleController::invalidate` | Bearer JWT + admin/admin SIGAME |
| `POST` | `/api/v1/activities/{id}/enrollments` | `ActivityParticipationController::enroll` | Bearer JWT + membro aprovado |
| `DELETE` | `/api/v1/activities/{id}/enrollments/me` | `ActivityParticipationController::cancelEnrollment` | Bearer JWT + inscrito |
| `POST` | `/api/v1/activities/{id}/check-in` | `ActivityParticipationController::checkIn` | Bearer JWT + inscrito |
| `GET` | `/api/v1/activities/{id}/attendances` | `ActivityParticipationController::listAttendances` | Bearer JWT + admin/supervisor/admin SIGAME |
| `POST` | `/api/v1/activities/{id}/attendances/{attendanceId}/validate` | `ActivityParticipationController::validateAttendance` | Bearer JWT + admin/supervisor/admin SIGAME |
| `GET` | `/api/v1/users/me/activities` | `MyActivitiesController::list` | Bearer JWT |
| `GET` | `/api/v1/notifications` | `NotificationController::list` | Bearer JWT |
| `GET` | `/api/v1/notifications/unread-count` | `NotificationController::unreadCount` | Bearer JWT |
| `POST` | `/api/v1/notifications/{id}/read` | `NotificationController::markRead` | Bearer JWT |
| `POST` | `/api/v1/notifications/read-all` | `NotificationController::markAllRead` | Bearer JWT |
| `GET` | `/api/v1/notifications/realtime` | `NotificationController::realtime` | Bearer JWT |
| `GET` | `/api/v1/admin/users` | `AdminUserController::list` | Bearer JWT + `ROLE_ADMIN_SIGAME` |
| `GET` | `/api/v1/admin/users/{id}` | `AdminUserController::show` | Bearer JWT + `ROLE_ADMIN_SIGAME` |
| `GET` | `/api/v1/admin/users/{id}/status-history` | `AdminUserController::statusHistory` | Bearer JWT + `ROLE_ADMIN_SIGAME` |
| `POST` | `/api/v1/admin/users/{id}/suspend` | `AdminUserController::suspend` | Bearer JWT + `ROLE_ADMIN_SIGAME` |
| `POST` | `/api/v1/admin/users/{id}/reactivate` | `AdminUserController::reactivate` | Bearer JWT + `ROLE_ADMIN_SIGAME` |
| `GET` | `/api/health/liveness` | `HealthController::liveness` | Pública |
| `GET` | `/api/health/readiness` | `HealthController::index` | Pública |

A rota `/users/me` precisa ser declarada antes de `/users/{id}` ou o parâmetro `{id}` precisa de um requirement que não aceite o literal `me`.

A organização das tags e grupos do Scalar, a convenção de summaries e o versionamento estão em [api-documentation.md](api-documentation.md).

---

## 8. Estrutura de testes

Os testes são divididos por **tipo** — unidade, funcional e integração — e cada grupo espelha a modularização do sistema: `tests/<Tipo>/<Módulo>/<Camada>/...` segue o caminho de `src/<Módulo>/<Camada>/...`. Testes que atravessam módulos ficam em `tests/Unit/Architecture/`.

```text
tests/
├── Unit/                               # grupo "unit"
│   ├── Architecture/
│   │   ├── ErrorCodeCatalogTest.php
│   │   └── ModuleBoundariesTest.php
│   ├── Identity/
│   │   ├── Domain/
│   │   │   ├── Error/IdentityErrorsTest.php
│   │   │   ├── RefreshToken/Entity/RefreshTokenTest.php
│   │   │   └── User/
│   │   │       ├── Entity/UserTest.php
│   │   │       ├── Entity/UserIdentityTest.php
│   │   │       ├── Exception/UserExceptionTest.php
│   │   │       └── ValueObject/
│   │   ├── Application/
│   │   │   ├── Service/TokenPairIssuerTest.php
│   │   │   └── UseCase/
│   │   │       ├── RegisterUser/RegisterUserHandlerTest.php
│   │   │       └── AuthenticateWithSocialProvider/AuthenticateWithSocialProviderHandlerTest.php
│   │   └── Infrastructure/
│   │       ├── Persistence/ORM/Type/
│   │       ├── Security/
│   │       └── Social/GoogleIdentityProviderTest.php
│   ├── Groups/
│   │   ├── Domain/
│   │   │   ├── Error/GroupErrorsTest.php
│   │   │   └── Group/
│   │   │       ├── Entity/GroupTest.php
│   │   │       └── ValueObject/GroupValueObjectsTest.php
│   │   └── Application/UseCase/
│   │       ├── CreateGroup/CreateGroupHandlerTest.php
│   │       ├── ApproveMembership/ApproveMembershipHandlerTest.php
│   │       └── ChangeMembershipRole/ChangeMembershipRoleHandlerTest.php
│   ├── Activities/
│   │   ├── Domain/Activity/
│   │   │   ├── Entity/ActivityTest.php
│   │   │   └── Policy/ActivityPolicyTest.php
│   │   └── Application/UseCase/CloseExpiredActivities/CloseExpiredActivitiesHandlerTest.php
│   ├── Notifications/
│   │   ├── Domain/Notification/Entity/NotificationTest.php
│   │   ├── Application/Service/{NotificationPlannerTest,NotificationChannelPolicyTest}
│   │   └── Infrastructure/Mercure/{MercureRealtimePublisherTest,MercureStreamAuthorizationProviderTest}
│   └── Shared/
│       ├── Domain/{Error,Exception,Functional,ValueObject}
│       ├── Infrastructure/{OpenApi,Persistence/ORM/Type}
│       └── Presentation/Http/{OpenApi,Response}
│
├── Functional/                         # grupo "functional"
│   ├── Identity/Presentation/Http/
│   │   ├── AuthTest.php
│   │   ├── MeTest.php
│   │   └── RegistrationTest.php
│   ├── Monitoring/Presentation/Http/HealthTest.php
│   ├── Groups/Presentation/Http/
│   │   ├── GroupTest.php
│   │   └── GroupMembershipTest.php
│   ├── Activities/Presentation/Http/ActivityTest.php
│   ├── Notifications/Presentation/Http/NotificationTest.php
│   ├── Identity/Presentation/Http/AdminUserTest.php
│   └── Shared/Presentation/Http/
│       ├── ApiDocumentationTest.php
│       └── HttpErrorHandlingTest.php
│
├── Integration/                        # grupo "integration" (adaptadores reais, sem HTTP)
│   ├── Identity/Infrastructure/Persistence/
│   │   ├── DoctrineUserRepositoryTest.php
│   │   ├── DoctrineRefreshTokenRepositoryTest.php
│   │   └── DoctrineUserSuspensionRepositoryTest.php
│   ├── Groups/Infrastructure/Persistence/DoctrineGroupRepositoryTest.php
│   ├── Activities/Infrastructure/Persistence/DoctrineActivityRepositoryTest.php
│   └── Notifications/Infrastructure/Persistence/DoctrineNotificationRepositoryTest.php
│
└── Helpers/                            # infraestrutura transversal de testes
    ├── ApiClient.php
    └── Factories/HasBrFakerTrait.php
```

Os grupos são declarados em `tests/Pest.php` e rodados com `composer test:unit` / `composer test:functional` / `composer test:integration` (ou `just test-unit` / `just test-functional` / `just test-integration`).

### 8.1 Tipos de teste

| Tipo | O que cobre | Ferramenta |
|---|---|---|
| Unidade | Entidades, VOs, policies e casos de uso (handlers) com ports mockadas | PestPHP + Mockery |
| Funcional | Fronteira HTTP: rotas, status code, payload, serialização, autenticação | PestPHP + WebTestCase + BrowserKit |
| Integração | Adaptadores reais: repositórios/banco, storage, JWT, hasher, eventos | PestPHP + Doctrine (sem BrowserKit) |

### 8.2 Isolamento do banco

Testes que tocam o banco (`integration` e `functional`) usam a extensão `Zenstruck\Foundry\PHPUnit\FoundryExtension` (registrada em `phpunit.xml.dist` com `enabled-auto-reset=true`). Com o `dama/doctrine-test-bundle` habilitado no env `test`, cada teste roda dentro de uma transação e sofre rollback ao final, e o banco é criado/migrado uma vez por execução:

- `zenstruck_foundry.orm.reset.mode: migrate` faz o schema vir das migrations, não do mapping;
- o reset por teste deixa de acontecer;
- dados criados no teste desaparecem no rollback, então fixtures de banco vão em `beforeEach` ou no corpo do teste — nunca em `beforeAll`/`setUpBeforeClass`.

Como cada teste está dentro de uma transação, `CREATE DATABASE`, `TRUNCATE` e `commit()`/`rollBack()` explícitos quebram o isolamento.

### 8.3 Como escrever testes

1. **Mockery para as ports** — dependências de um handler são simuladas com `Mockery::mock(Interface::class)` em `beforeEach`, com `shouldReceive(...)->once()->with(...)`, `Mockery::on(...)` e `shouldNotReceive(...)`. Nunca `createMock()` do PHPUnit.
2. **Sem repetir `->value()`** — extraia o resultado para uma variável antes das asserções.
3. **Datasets do Pest quando reduzem repetição real** — casos parametrizados vão em `->with(...)`, com chaves string para nomear os casos.
4. **Setup repetido vira helper** — quando a repetição é de construção, extraia uma função no próprio arquivo de teste (`makeUser()`).

O fluxo completo de comandos e a configuração do banco de teste estão em [desenvolvimento.md](desenvolvimento.md#5-testes).

---

## 9. Decisões e acompanhamento

- **Decisões consolidadas e o porquê:** [decisoes.md](decisoes.md)
- **Estado da implementação, roadmap, decisões abertas e migrations:** [implementacao.md](implementacao.md)
- **Requisitos funcionais e critérios de aceitação:** [requisitos/levantamento-de-requisitos.md](requisitos/levantamento-de-requisitos.md)
