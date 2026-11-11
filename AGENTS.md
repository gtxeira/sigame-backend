# AGENTS.md

SIGAME is a JSON HTTP API (no app UI) built with PHP 8.5 / Symfony 8.1, PostgreSQL 18
and FrankenPHP. `src/` is a modular monolith: each module lives in
`src/<Module>/{Domain,Application,Infrastructure,Presentation}`. The implemented
modules are `Identity`, `Groups`, `Activities`, `Gamification` and `Notifications`
(business), plus `Monitoring` (technical); `Shared` is the kernel.
Read `docs/implementacao.md` before starting a feature, `docs/arquitetura.md` for
boundaries, and `docs/desenvolvimento.md` for the full workflow.

If the scope is ambiguous (new module, persistence, auth, storage), ask or state the
assumption; don't scaffold a full stack unprompted.

## Commands run inside Docker

- The supported toolchain is the `php` container. Use `just ...` (or
  `docker compose exec php ...`), not host `php`, `composer`, `symfony serve`, or
  `bin/console`.
- Create `.env.local` once (`touch .env.local`). The justfile passes `.env`,
  `.env.dev` and `.env.local` to Compose; a missing `.env.local` breaks every recipe.
- `just build`, `just up`, `just logs`, `just down`, `just sh` / `just bash`.
- `just sf <args>` runs `bin/console`; `just composer <args>` runs Composer;
  `just cc` clears the cache. Admin provisioning uses `just sf app:identity:grant-admin <email>`
  and `just sf app:identity:revoke-admin <email>`.
- Messenger runs in the `worker` service. Use `just worker-logs`, `just worker`
  (manual consumer) and `just messenger-failed` (failed queue); `just keygen`
  generates `MESSENGER_MAIL_ENCRYPTION_KEY`.
- Add packages with `just composer req <package>` and let Flex register the bundle and
  recipe config. Don't hand-edit `config/bundles.php`.
- App: https://api.sigame.localhost:8443, OpenAPI UI `/doc`, JSON `/doc.json`.
  The local TLS certificate is self-signed.
- The entrypoint installs `vendor/` if missing, waits for PostgreSQL, and applies
  pending migrations on boot.

## Verification order

- Full gate: `just composer quality` (cs:check -> phpstan -> test).
- `just cs-check` / `just cs-fix`; `just phpstan` warms `var/cache/dev` first because
  PHPStan reads the generated container XML.
- Tests run through Pest, not a bare `bin/phpunit`:
  `just test`, `just test-unit`, `just test-functional`, `just test-integration`.
- Focused runs: `just test --filter="rotates both tokens"` or
  `just test tests/Functional/Identity/Presentation/Http/RegistrationTest.php`.
- `just test` creates the `_test` database first; `just test-unit` and a direct
  `vendor/bin/pest` invocation do not.

## Architecture rules (enforced by tests)

- `Shared` must not import a feature module. A feature module may import only itself
  and `Shared`.
- Cross-module communication goes through Domain Events
  (`DomainEventInterface` + `#[AsEventListener]`), never direct imports.
- Transactional identity email is enqueued through `IdentityMailDispatcherInterface`; use cases
  must not call `IdentityMailerInterface` directly. The raw token is encrypted with
  `PayloadEncryptor` before it reaches Messenger.
- Notifications are written through the `Notifications` module. Domain events only feed
  listeners; use cases must not call channels directly. The in-app record is canonical and
  external channels are adapters. The realtime transport is Mercure and must not become the
  source of truth.
- Keep layers clean: Domain is framework-free; Application holds ports/use cases;
  Infrastructure implements adapters; Presentation handles HTTP.
- `tests/Unit/Architecture/ModuleBoundariesTest.php` fails on violations.
  Mirror source paths in `tests/<Type>/<Module>/...`.
- Notifications may import event classes from other modules (ADR-030); all other imports follow
  the module boundary rules. Cross-module read ports live in `Shared/Application`.
- A new module must register its Doctrine mapping in
  `config/packages/doctrine.php`; mappings are not auto-discovered.

## Error and HTTP contract

### Result vs exception

Choose by the nature of the failure, not by convenience:

- **Expected and recoverable -> `Result` with a `DomainError` list.** Input rejected,
  business-rule conflict, credential/token failure, or any state the client can act on.
  Never throw for these.
- **Input and Value Object validation -> `ValidationResult`.** Accumulate independent
  errors and cross the use-case boundary with `Result::fromValidation()`.
- **Idempotent no-op -> don't throw.** When the resource is already in the requested
  state, return success (e.g. `bool` telling whether a transition happened, or a
  `Result` success) instead of an "already done" exception.
- **Broken invariant or programmer error -> exception.** Impossible states, invalid VO
  arguments from trusted code, API misuse. If the client is not meant to see it, use
  `LogicException` (or a dedicated internal exception) and let it surface as 500; do
  not add it to the public error catalog.
- **Client-facing domain failure that cannot return a value -> `DomainException`.**
  A concrete subclass per error for a VO/entity invariant the client must see. It must
  declare `errorCode()`, `kind()`, `publicMessage()` and `errorDetails()`.
- **Adapter boundary -> typed exception converted at the use case.** Infrastructure
  failures (Doctrine constraint, JWT parsing, external provider, crypto, Messenger)
  throw a typed exception; the use case catches it and returns a `Result`. Examples:
  unique-constraint races -> `EmailAlreadyExistsException` /
  `CpfAlreadyExistsException` / `SocialIdentityAlreadyLinkedException`;
  `InvalidTokenException`; `SocialAuthenticationException`; `PayloadDecryptionFailed`.
- **Framework contracts -> keep the framework exception.** Symfony Security, Messenger
  and HTTP exceptions must not be replaced by `Result`.
- **Configuration/boot errors -> fail fast.** Throw in constructors/service wiring so
  the container does not boot with invalid configuration.

### Validation ownership and error accumulation

- Each rule has one owner: DTOs validate structure, type and presence; Value Objects
  validate field semantics; aggregates, policies and repositories validate state and
  business rules. Never duplicate the same rule in the DTO and the Value Object.
- Independent field validations accumulate: evaluate them in a single phase and return
  them together via `ValidationResult::combine()` or `Result::failure([...])`. Do not
  stop at the first field error when the remaining checks do not depend on state or I/O.
- Stop at the first error only when the next check depends on the previous value/state
  (access guard, aggregate existence, state transition, lock/conflict) or when
  accumulating would leak information.
- Field-shape errors use `ValidationErrorCode`; business, conflict, authentication,
  state and not-found errors use the module catalog. `VALIDATION_ERROR` does not exist.
- Every error is a flat item `{ code, field?, message }`: one item per failure, no
  grouping, no `details`. `field` is optional and uses the current Symfony property
  path (`modalities[0]`).
- Deduplicate by `(code, field)`; when a field fails as `FIELD_REQUIRED`, drop the other
  errors for that field.
- Required DTO fields declare an empty default (`''`, `[]`, `null`) so a missing field
  yields `FIELD_REQUIRED` instead of a denormalization type error.

Additional rules:

- Don't use generic `\RuntimeException` or SPL `\InvalidArgumentException` for expected
  business failures. Expected failures are `Result`; internal invariants are
  `LogicException` or a concrete domain exception.
- Don't add internal or unreachable codes to the public catalog; `ApiDocumentationTest`
  asserts they never leak into the OpenAPI document.
- Create errors only through the catalog and factory: `ValidationErrorCode` /
  `ValidationErrors` for field shape, and the module `*ErrorCode` / `*Errors` for
  business errors; add the case to `ErrorCodeCatalogTest`. The code string is a public
  contract; public messages are PT-BR.
- Never hardcode an HTTP status in a controller. `ResultResponder` and
  `ErrorStatusResolver` derive it from `ErrorKind`, with precedence
  `Authentication > Forbidden > Validation > Conflict`.
- Controllers extend `AbstractController`, stay thin, bind input with
  `#[MapRequestPayload]` / `#[MapQueryString]`, and delegate to `__invoke` handlers.
- `/api/v1` is declared on the controller class; route names use `app_...`.
  A new public route must be added to `access_control` in
  `config/packages/security.php`, otherwise it falls under `ROLE_USER`.
- Reuse the response/error/OpenAPI helpers in
  `src/Shared/Presentation/Http/` instead of hand-building payloads.
- OpenAPI uses attributes only. Summaries are infinitive, sentence case, at most
  60 characters, no trailing period. New tags must be declared in
  `config/packages/nelmio_api_doc.php` and assigned to an `x-tagGroups` group.
  `ApiDocumentationTest` enforces this and that `#[IsGranted]` matches `#[Security]`.

## Configuration and persistence

- Config is PHP only (`config/*.php`), not YAML/XML. Rely on autowiring; use
  `#[Autowire]` / `#[Target]` in the class when a type-hint is not enough.
  `config/reference.php` is generated; don't edit it.
- Schema changes: `just sf make:migration`, review the migration, then
  `just sf doctrine:migrations:migrate --no-interaction`. Never use
  `doctrine:schema:update` or hand-written SQL.
- Tests build the schema from migrations (`Foundry reset.mode: migrate`), so an entity
  change without a migration breaks the suite.
- Register custom DBAL types in `config/packages/doctrine.php` and add their unit test.
- `.env` holds committed defaults only. Secrets and overrides go in `.env.local`
  (git-ignored) or the Symfony vault; `.env.dev` and `.env.test` hold environment
  defaults. `MESSENGER_MAIL_ENCRYPTION_KEY` is a base64 32-byte key required by the
  web container and the worker.
- `messenger_messages` is created by migration; keep `auto_setup=0` and do not route
  `Symfony\Component\Mailer\Messenger\SendEmailMessage` to `async` (the mailer bus is
  disabled on purpose).
- Not implemented yet: API Platform and shared storage for rate limiter and OAuth flow.
  Don't assume or scaffold them unless asked.

## Test conventions

- Unit tests mock ports with Mockery (`Mockery::mock(...)->shouldReceive(...)`), not
  PHPUnit's `createMock()`.
- Functional tests use the `$this->api` helper with the route name:
  `$this->api->post('app_auth_login', ...)`. Never build URLs by hand.
- DB tests run in a DAMA transaction with rollback. Don't call `TRUNCATE`,
  `CREATE DATABASE`, `commit()` or `rollBack()`, and don't create DB data in
  `beforeAll` / `setUpBeforeClass`.
- Prefer the existing `faker()` (pt_BR), factories and helpers over new fixtures.
- Pest datasets use string keys; extract values once instead of repeating `->value()`.

## Style and commits

- Every PHP file starts with `declare(strict_types=1);`. php-cs-fixer applies
  `@Symfony` plus project rules (short arrays, ordered imports, trailing commas, no
  Yoda style).
- Test descriptions are in English; user-facing messages, comments and docs are PT-BR.
- Conventional Commits are enforced by CaptainHook, e.g.
  `feat(identity): adicionar onboarding`. Pre-commit runs php-cs-fixer and PHPStan on
  staged files and requires the containers to be running.
- CI does not run the test suite (the PHPUnit/migration steps are commented out); run
  the quality gate locally before declaring work done.
- When unsure, read the relevant doc: `docs/desenvolvimento.md`, `docs/arquitetura.md`,
  `docs/autenticacao.md`, `docs/api-documentation.md`, `docs/infraestrutura.md`,
  `docs/decisoes.md`.
