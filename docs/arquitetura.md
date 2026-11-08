# SIGAME — Arquitetura do Sistema

> **Status:** implementado para o módulo Identity; módulos de negócio planejados
>
> **Versão:** 0.2
>
> **Última atualização:** 2026-11-08
>
> **Autor:** Gustavo Teixeira de Sousa

Roadmap, decisões em aberto e dívidas técnicas ficam em [implementacao.md](implementacao.md). O porquê das decisões consolidadas fica em [decisoes.md](decisoes.md).

---

## 1. Visão geral

O SIGAME é um **monolito modular** estruturado com **Clean Architecture**. Cada módulo funcional possui suas próprias camadas (`Domain → Application → Infrastructure → Presentation`). A comunicação entre módulos ocorre por **Domain Events**, sem importação direta de classes concretas.

O módulo `Shared` funciona como kernel: concentra value objects, catálogo de erros, eventos, persistência base e a infraestrutura HTTP comum. Módulos funcionais importam apenas a si próprios e `Shared`.

Hoje o único módulo de negócio implementado é **Identity**. `Monitoring` existe como módulo técnico, sem camada de domínio. Os demais módulos estão planejados e descritos em [implementacao.md](implementacao.md#2-roadmap).

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
- **Status HTTP é derivado, nunca hardcoded no controller**: `ErrorStatusResolver` mapeia `Authentication` → 401, `Forbidden` → 403, `Validation` → 422 e `Conflict` → 409. A precedência é `Authentication` > `Forbidden` > `Validation` > `Conflict`; o corpo continua trazendo todos os erros.
- **`DomainException` também declara `kind()`**, de modo que os dois caminhos (exceção e `Result`) usam a mesma regra de status.
- Códigos transversais da camada HTTP ficam em `ApiErrorCode` (`BAD_REQUEST`, `UNAUTHORIZED`, `ACCESS_TOKEN_MISSING`, `ACCESS_TOKEN_INVALID`, `ACCESS_TOKEN_EXPIRED`, `FORBIDDEN`, `ORIGIN_NOT_ALLOWED`, `NOT_FOUND`, `VALIDATION_ERROR`, `TOO_MANY_REQUESTS`, `INTERNAL_ERROR`, etc.) e não são erros de domínio.
- **Mensagens públicas em PT-BR**: `DomainError::$message`, `DomainException::publicMessage()` e os `message` de `errorDetails()` são exibidos ao cliente da API.

### 2.2 Regras de fronteira entre módulos

Estas regras são verificadas automaticamente por `tests/Unit/Architecture/ModuleBoundariesTest.php`:

1. **`Shared` é o kernel, não um depósito** — não pode importar nenhum módulo funcional (`Identity`, `Monitoring`, futuros módulos). Tudo que é específico de um módulo fica dentro dele.
2. **Módulo funcional importa apenas a si próprio e `Shared`** — um módulo não referencia classes concretas de outro módulo. A comunicação é feita por Domain Events.
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
│   │   └── Event/                           # EventDispatcherInterface
│   ├── Infrastructure/
│   │   ├── Persistence/AbstractRepository.php
│   │   ├── Persistence/ORM/Type/            # BaseIdType, CpfType, EmailType, InstantType
│   │   ├── Event/SymfonyEventDispatcherAdapter.php
│   │   └── OpenApi/                         # ExampleValueResolver, FillNamedExampleValues
│   └── Presentation/Http/
│       ├── ExceptionListener/               # Api, Domain, Http e Validator
│       ├── OpenApi/                         # respostas e exemplos reutilizáveis
│       ├── RateLimit/RateLimitGuard.php
│       ├── Response/                        # ApiResponse, ResultResponder, ErrorStatusResolver, etc.
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

### 3.1 Módulos planejados

Quando implementados, devem seguir o mesmo padrão:

| Módulo | Responsabilidade |
|---|---|
| `Groups` | Grupos, membros, supervisores e comunicação externa |
| `Activities` | Atividades, check-in, presença e conflito de horário |
| `Gamification` | XP, níveis, deleveling e recompensas |
| `Moderation` | Denúncias, bloqueios e suspensão global |
| `Sponsorship` | Patrocinadores, benefícios e vouchers |
| `Notifications` | Alertas internos |
| `Reports` | Indicadores mensais dos grupos |

O estado de cada módulo está em [implementacao.md](implementacao.md).

---

## 4. Modelo de domínio — Identity

### 4.1 Agregado `User`

A raiz do agregado mantém perfil, acesso e nível. Credenciais de login ficam em `user_identities` (1:N), e as modalidades em `user_modalities`.

| Componente | Responsabilidade | Colunas / tabela |
|---|---|---|
| `User` | Raiz do agregado | `id`, `cpf`, `level`, `onboarding_status`, `created_at`, `updated_at` |
| `Profile` | Dados de perfil e biometria | `name`, `height_cm`, `weight_kg`, `photo_url` |
| `AccessState` | Autenticação/autorização (RN18) | `role`, `status` |
| `UserIdentity` | Login local ou social | tabela `user_identities` |
| `UserModality` | Modalidades do usuário | tabela `user_modalities` |

Comportamentos principais de `User`:

- `register()` — cadastro local com e-mail e senha; cria a identidade local.
- `registerWithProvider()` — cadastro social, sem senha e sem CPF.
- `linkSocialIdentity()` — vincula um provedor a uma conta existente.
- `assignCpf()` — define o CPF uma única vez (write-once).
- `completeOnboarding()` — valida dados, define modalidades e concede nível 2.
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

A camada HTTP do onboarding expõe `POST /api/v1/users/me/onboarding`. O estado está em [implementacao.md](implementacao.md).

### 5.2 Eventos

| Evento | Disparado quando | Consumidores |
|---|---|---|
| `UserRegistered` | Após cadastro local ou social | Nenhum implementado; previsto para Gamification |
| `OnboardingCompleted` | Após onboarding válido | Nenhum implementado; previsto para Gamification |

### 5.3 Fluxos planejados

```text
Identity  --UserRegistered--------> Gamification   (cria UserProgress, nível 1)
Identity  --OnboardingCompleted---> Gamification   (concede nível 2)
Activities --AttendanceValidated--> Gamification   (awards XP)
Activities --ActivityCreated------> Notifications  (alerta participantes)
Groups    --GroupCreated----------> Notifications
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
| `FileStorageInterface` (planejado) | `FlysystemS3Adapter` (planejado) | `league/flysystem` |

O adapter de storage ainda não foi implementado. A decisão está em [decisoes.md](decisoes.md#adr-010--arquivos-em-s3-via-flysystemgarage).

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
│   └── Shared/Presentation/Http/
│       ├── ApiDocumentationTest.php
│       └── HttpErrorHandlingTest.php
│
├── Integration/                        # grupo "integration" (adaptadores reais, sem HTTP)
│   └── Identity/Infrastructure/Persistence/
│       ├── DoctrineUserRepositoryTest.php
│       └── DoctrineRefreshTokenRepositoryTest.php
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
