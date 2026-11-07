# AGENTS.md

SIGAME is a JSON HTTP API (no app UI) built with PHP 8.5 / Symfony 8.1, PostgreSQL 18
and FrankenPHP. `src/` is a modular monolith: each module lives in
`src/<Module>/{Domain,Application,Infrastructure,Presentation}`. The implemented
modules are `Identity` (business) and `Monitoring` (technical); `Shared` is the kernel.
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
  `just cc` clears the cache.
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
- Transactional email is enqueued through `IdentityMailDispatcherInterface`; use cases
  must not call `IdentityMailerInterface` directly. The raw token is encrypted with
  `PayloadEncryptor` before it reaches Messenger.
- Keep layers clean: Domain is framework-free; Application holds ports/use cases;
  Infrastructure implements adapters; Presentation handles HTTP.
- `tests/Unit/Architecture/ModuleBoundariesTest.php` fails on violations.
  Mirror source paths in `tests/<Type>/<Module>/...`.
- A new module must register its Doctrine mapping in
  `config/packages/doctrine.php`; mappings are not auto-discovered.

## Error and HTTP contract

- Expected business failure -> `Result` with `DomainError` list. Broken invariant ->
  `DomainException`. Don't use generic exceptions for expected cases.
- Create errors only through the module's `*ErrorCode` catalog and `*Errors` factory;
  add the case to `ErrorCodeCatalogTest`. The code string is a public contract; public
  messages are PT-BR.
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
- Not implemented yet: S3/Flysystem storage adapter, onboarding HTTP layer,
  API Platform, shared storage for rate limiter and OAuth flow. Don't assume or scaffold
  them unless asked.

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
