# SIGAME — Implementação e acompanhamento

> **Status:** documento de trabalho
>
> **Última atualização:** 2026-11-11
>
> **Finalidade:** registrar o estado da implementação, o roadmap, as decisões ainda abertas, o histórico de migrations e
> as dívidas técnicas.

Este documento concentra o que é **acompanhamento de implementação**. Ele não substitui:

- [arquitetura.md](arquitetura.md) — princípios, módulos, camadas e padrões;
- [decisoes.md](decisoes.md) — decisões consolidadas e o porquê de cada uma;
- [requisitos/levantamento-de-requisitos.md](requisitos/levantamento-de-requisitos.md) — requisitos funcionais, regras
  de negócio e critérios de aceitação.

Quando este documento divergir dos requisitos, a decisão de produto deve ser registrada em `decisoes.md` e o requisito
atualizado no levantamento.

---

## 1. Estado atual

### 1.1 Fases

| Fase | Escopo                                                              | Estado                                                 |
|------|---------------------------------------------------------------------|--------------------------------------------------------|
| 0    | Infraestrutura base (Docker, PostgreSQL, Doctrine, Shared, storage) | Concluída                                              |
| 1    | Identity: cadastro                                                  | Concluída                                              |
| 2    | Identity: autenticação                                              | Concluída                                              |
| 3    | Identity: onboarding                                                | Concluída                                              |
| 4    | Groups                                                              | Concluída                                              |
| 5    | Activities                                                          | Concluída                                              |
| 6    | Gamification                                                        | Em andamento (núcleo implementado; recálculo pendente) |
| 7    | Notifications + Administração de contas                             | Concluída                                              |
| 8    | Sponsorship + Reports                                               | Não iniciada                                           |

### 1.2 Capacidades

| Capacidade                                                                                                                             | Estado        | Evidência principal                                                                                           |
|----------------------------------------------------------------------------------------------------------------------------------------|---------------|---------------------------------------------------------------------------------------------------------------|
| Cadastro local com CPF opcional                                                                                                        | Implementado  | `src/Identity/Application/UseCase/RegisterUser/`                                                              |
| Login com e-mail e senha                                                                                                               | Implementado  | `src/Identity/Application/UseCase/AuthenticateUser/`                                                          |
| Login social com Google via authorization code + state/PKCE                                                                            | Implementado  | `src/Identity/Application/UseCase/Social/` e `src/Identity/Infrastructure/Social/`                            |
| Vínculo explícito de identidade social                                                                                                 | Implementado  | `LinkSocialIdentity/` e `POST /api/v1/auth/social/{provider}/link`                                            |
| Verificação de e-mail                                                                                                                  | Implementado  | `src/Identity/Application/UseCase/EmailVerification/` e `src/Identity/Infrastructure/Mailer/`                 |
| E-mail transacional assíncrono                                                                                                         | Implementado  | `IdentityMailDispatcherInterface`, `MessengerIdentityMailDispatcher` e worker do Messenger                    |
| Recuperação e troca de senha                                                                                                           | Implementado  | `src/Identity/Application/UseCase/Password/`                                                                  |
| Access token JWT + refresh token opaco                                                                                                 | Implementado  | `src/Identity/Application/Service/TokenPairIssuer.php`                                                        |
| Rotação de refresh com detecção de reuso                                                                                               | Implementado  | `TokenPairIssuer::rotate()`                                                                                   |
| Logout e logout de todos os dispositivos                                                                                               | Implementado  | `src/Identity/Application/UseCase/Logout/`                                                                    |
| Dados do usuário autenticado                                                                                                           | Implementado  | `src/Identity/Presentation/Http/Controller/MeController.php`                                                  |
| Definição de CPF write-once                                                                                                            | Implementado  | `AssignCpfHandler` e `PATCH /api/v1/users/me/cpf`                                                             |
| Data de nascimento write-once e restrição a maiores de 18                                                                              | Implementado  | `BirthDate`, `AssignBirthDateHandler` e `PATCH /api/v1/users/me/birth-date`                                   |
| Rate limiting de cadastro, login, social, e-mail, senha, refresh, CPF, perfil, fotos, grupos, atividades, notificações e administração | Implementado  | `config/packages/rate_limiter.php`                                                                            |
| Health checks                                                                                                                          | Implementado  | `src/Monitoring/`                                                                                             |
| Onboarding de modalidades                                                                                                              | Implementado  | `CompleteOnboardingHandler` e `POST /api/v1/users/me/onboarding`                                              |
| Edição parcial de perfil                                                                                                               | Implementado  | `UpdateProfileHandler` e `PATCH /api/v1/users/me/profile`                                                     |
| Upload e remoção de foto de perfil em S3                                                                                               | Implementado  | `ProfilePhotoStorageInterface`, `S3ProfilePhotoStorage` e `POST`/`DELETE /api/v1/users/me/photo`              |
| Criação e listagem de grupos                                                                                                           | Implementado  | `CreateGroupHandler`, `ListGroupsHandler`, `GET`/`POST /api/v1/groups` e `GET /api/v1/users/me/groups`        |
| Solicitação e decisão de entrada                                                                                                       | Implementado  | `RequestMembershipHandler`, `ApproveMembershipHandler`, `RejectMembershipHandler` e rotas de memberships      |
| Administração de membros e papéis                                                                                                      | Implementado  | `RemoveMemberHandler`, `ChangeMembershipRoleHandler` e `PATCH .../role`                                       |
| Criador independente e múltiplos admins                                                                                                | Implementado  | `groups.created_by`, `Membership.role` e invariante do último admin                                           |
| Ciclo de vida do grupo                                                                                                                 | Implementado  | Arquivamento, reativação, exclusão lógica, saída do membro e arquivamento automático (`scheduler_groups`)       |
| Supervisor do grupo                                                                                                                    | Implementado  | `DesignateSupervisorHandler`, `ClearSupervisorHandler` e `PUT`/`DELETE .../supervisor`                        |
| Comunicação externa do grupo                                                                                                           | Implementado  | `communicationLink` visível apenas para membros aprovados                                                     |
| CRUD administrativo completo de usuários                                                                                               | Parcial       | Listagem, busca, detalhe, suspensão e reativação implementados; edição/exclusão fora da V1                    |
| Atividades e ciclo de vida                                                                                                             | Implementado  | `Activity`, `ActivityController`, `ActivityLifecycleController` e migration `Version20261109224939`           |
| Inscrição e conflito de horário                                                                                                        | Implementado  | `EnrollInActivityHandler`, `DoctrineActivityRepository::hasConflictingAttendance()`                           |
| Check-in e validação de presença                                                                                                       | Implementado  | `CheckInHandler`, `ValidateAttendanceHandler` e `AttendanceValidated`                                         |
| Fechamento automático de atividades                                                                                                    | Implementado  | `ActivityClosingSchedule`, `CloseExpiredActivitiesHandler` e serviço `scheduler`                              |
| Gamificação — fundação, ledger e XP                                                                                                    | Implementado  | `UserProgress`, `XpEvent`, `LevelCurve` e `ActivityXpPolicy`                                                  |
| Ofensiva semanal                                                                                                                       | Implementado  | `WEEKLY_START`, `WEEKLY_GOAL`, `StreakCalculator` e `WeeklyBonusAwarder`                                      |
| Deleveling por inatividade                                                                                                             | Implementado  | `DelevelingPolicy`, `ApplyInactivityDecayHandler` e schedule diário                                           |
| API de progresso e histórico de XP                                                                                                     | Implementado  | `GET /users/me/progress` e `/progress/history`                                                                |
| Catálogo de regras de gamificação                                                                                                      | Implementado  | `GET /api/v1/gamification/rules`, `GetGamificationRulesHandler` e `ActivityXpTable`                           |
| Versionamento e recálculo de XP                                                                                                        | Parcial       | `rule_version` gravado; comando `app:gamification:recalculate` pendente                                       |
| Notificações in-app, leitura e sino                                                                                                    | Implementado  | `Notification`, `NotificationController`, `CreateNotificationsHandler` e migration `Version20261110145733`    |
| Notificações em tempo real via Mercure                                                                                                 | Implementado  | `MercureRealtimePublisher`, `MercureStreamAuthorizationProvider` e `GET /api/v1/notifications/realtime`       |
| Canal e-mail para eventos de conta                                                                                                     | Implementado  | `EmailNotificationChannel` e `notification_deliveries`                                                        |
| Administração global de contas                                                                                                         | Implementado  | `AdminUserController`, `ListUsersHandler`, `SuspendUserHandler`, `ReactivateUserHandler` e `user_suspensions` |
| Demais módulos de negócio                                                                                                              | Não iniciados | Sponsorship, Reports                                                                                          |

### 1.3 O que existe hoje no HTTP

Rotas confirmadas por `bin/console debug:router`:

| Método   | Rota                                                          | Autenticação                               |
|----------|---------------------------------------------------------------|--------------------------------------------|
| `POST`   | `/api/v1/auth/register`                                       | Pública                                    |
| `POST`   | `/api/v1/auth/login`                                          | Pública                                    |
| `POST`   | `/api/v1/auth/social/{provider}/start`                        | Pública (Bearer opcional)                  |
| `POST`   | `/api/v1/auth/social/{provider}/callback`                     | Pública + cookie `social_flow`             |
| `POST`   | `/api/v1/auth/social/{provider}/link`                         | Bearer JWT                                 |
| `POST`   | `/api/v1/auth/email/verify`                                   | Pública                                    |
| `POST`   | `/api/v1/auth/password/forgot`                                | Pública                                    |
| `POST`   | `/api/v1/auth/password/reset`                                 | Pública                                    |
| `POST`   | `/api/v1/auth/refresh`                                        | Cookie `refresh_token`                     |
| `POST`   | `/api/v1/auth/logout`                                         | Cookie `refresh_token`                     |
| `POST`   | `/api/v1/auth/logout/all`                                     | Bearer JWT                                 |
| `GET`    | `/api/v1/users/me`                                            | Bearer JWT                                 |
| `PATCH`  | `/api/v1/users/me/cpf`                                        | Bearer JWT                                 |
| `PATCH`  | `/api/v1/users/me/birth-date`                                 | Bearer JWT                                 |
| `PATCH`  | `/api/v1/users/me/profile`                                    | Bearer JWT                                 |
| `POST`   | `/api/v1/users/me/photo`                                      | Bearer JWT                                 |
| `DELETE` | `/api/v1/users/me/photo`                                      | Bearer JWT                                 |
| `POST`   | `/api/v1/users/me/onboarding`                                 | Bearer JWT                                 |
| `POST`   | `/api/v1/auth/email/verification/resend`                      | Bearer JWT                                 |
| `POST`   | `/api/v1/users/me/password`                                   | Bearer JWT                                 |
| `POST`   | `/api/v1/users/me/identities/{provider}`                      | Bearer JWT                                 |
| `POST`   | `/api/v1/groups`                                              | Bearer JWT                                 |
| `GET`    | `/api/v1/groups`                                              | Bearer JWT                                 |
| `GET`    | `/api/v1/users/me/groups`                                     | Bearer JWT                                 |
| `GET`    | `/api/v1/groups/{id}`                                         | Bearer JWT                                 |
| `PATCH`  | `/api/v1/groups/{id}`                                         | Bearer JWT + admin do grupo                |
| `POST`   | `/api/v1/groups/{id}/archive`                                 | Bearer JWT + admin do grupo                |
| `POST`   | `/api/v1/groups/{id}/unarchive`                               | Bearer JWT + admin do grupo                |
| `DELETE` | `/api/v1/groups/{id}`                                         | Bearer JWT + responsável do grupo          |
| `DELETE` | `/api/v1/groups/{id}/membership`                              | Bearer JWT                                 |
| `POST`   | `/api/v1/groups/{id}/membership-requests`                     | Bearer JWT                                 |
| `GET`    | `/api/v1/groups/{id}/memberships`                             | Bearer JWT + admin do grupo                |
| `POST`   | `/api/v1/groups/{id}/memberships/{membershipId}/approve`      | Bearer JWT + admin do grupo                |
| `POST`   | `/api/v1/groups/{id}/memberships/{membershipId}/reject`       | Bearer JWT + admin do grupo                |
| `POST`   | `/api/v1/groups/{id}/memberships/{membershipId}/remove`       | Bearer JWT + admin do grupo                |
| `PATCH`  | `/api/v1/groups/{id}/memberships/{membershipId}/role`         | Bearer JWT + admin do grupo                |
| `PUT`    | `/api/v1/groups/{id}/owner`                                   | Bearer JWT + responsável do grupo          |
| `PUT`    | `/api/v1/groups/{id}/supervisor`                              | Bearer JWT + admin do grupo                |
| `DELETE` | `/api/v1/groups/{id}/supervisor`                              | Bearer JWT + admin do grupo                |
| `POST`   | `/api/v1/groups/{groupId}/activities`                         | Bearer JWT + admin do grupo/admin SIGAME   |
| `GET`    | `/api/v1/groups/{groupId}/activities`                         | Bearer JWT + membro aprovado               |
| `GET`    | `/api/v1/activities/{id}`                                     | Bearer JWT + membro aprovado               |
| `PATCH`  | `/api/v1/activities/{id}`                                     | Bearer JWT + admin do grupo/admin SIGAME   |
| `GET`    | `/api/v1/activities/{id}/reschedules`                         | Bearer JWT + admin do grupo/admin SIGAME   |
| `POST`   | `/api/v1/activities/{id}/cancel`                              | Bearer JWT + admin do grupo/admin SIGAME   |
| `POST`   | `/api/v1/activities/{id}/start`                               | Bearer JWT + admin/supervisor/admin SIGAME |
| `POST`   | `/api/v1/activities/{id}/finish`                              | Bearer JWT + admin/supervisor/admin SIGAME |
| `POST`   | `/api/v1/activities/{id}/invalidate`                          | Bearer JWT + admin do grupo/admin SIGAME   |
| `POST`   | `/api/v1/activities/{id}/enrollments`                         | Bearer JWT + membro aprovado               |
| `DELETE` | `/api/v1/activities/{id}/enrollments/me`                      | Bearer JWT + inscrito                      |
| `POST`   | `/api/v1/activities/{id}/check-in`                            | Bearer JWT + inscrito                      |
| `GET`    | `/api/v1/activities/{id}/attendances`                         | Bearer JWT + admin/supervisor/admin SIGAME |
| `POST`   | `/api/v1/activities/{id}/attendances/{attendanceId}/validate` | Bearer JWT + admin/supervisor/admin SIGAME |
| `GET`    | `/api/v1/users/me/activities`                                 | Bearer JWT                                 |
| `GET`    | `/api/v1/users/me/progress`                                   | Bearer JWT                                 |
| `GET`    | `/api/v1/users/me/progress/history`                           | Bearer JWT                                 |
| `GET`    | `/api/v1/gamification/rules`                                  | Bearer JWT                                 |
| `GET`    | `/api/v1/notifications`                                       | Bearer JWT                                 |
| `GET`    | `/api/v1/notifications/unread-count`                          | Bearer JWT                                 |
| `POST`   | `/api/v1/notifications/{id}/read`                             | Bearer JWT                                 |
| `POST`   | `/api/v1/notifications/read-all`                              | Bearer JWT                                 |
| `GET`    | `/api/v1/notifications/realtime`                              | Bearer JWT                                 |
| `GET`    | `/api/v1/admin/users`                                         | Bearer JWT + `ROLE_ADMIN_SIGAME`           |
| `GET`    | `/api/v1/admin/users/{id}`                                    | Bearer JWT + `ROLE_ADMIN_SIGAME`           |
| `GET`    | `/api/v1/admin/users/{id}/status-history`                     | Bearer JWT + `ROLE_ADMIN_SIGAME`           |
| `POST`   | `/api/v1/admin/users/{id}/suspend`                            | Bearer JWT + `ROLE_ADMIN_SIGAME`           |
| `POST`   | `/api/v1/admin/users/{id}/reactivate`                         | Bearer JWT + `ROLE_ADMIN_SIGAME`           |
| `GET`    | `/api/health/liveness`                                        | Pública                                    |
| `GET`    | `/api/health/readiness`                                       | Pública                                    |

Não existe página inicial (o front-end é separado). A documentação interativa fica em `/doc` e o JSON OpenAPI em
`/doc.json`.

---

## 2. Roadmap

O roadmap é organizado por fases entregáveis. Cada fase só é considerada concluída quando os testes que exercitam o
comportamento pelo caminho do chamador estiverem verdes.

### Fase 0 — Infraestrutura base

- [x] Instalar dependências de framework, segurança, serialização, validação, JWT, lock e rate limiting.
- [x] Configurar Docker Compose com FrankenPHP, PostgreSQL 18 e Garage (S3).
- [x] Configurar Doctrine, migrations e tipos customizados.
- [x] Estruturar `src/Shared/` com VOs base (`BaseId`, `Email`, `Cpf`, `Instant`, `Modality`).
- [x] Implementar adapter de storage S3 via Flysystem e ligar o upload de foto de perfil.

### Fase 1 — Identity: cadastro

- [x] Entidade `User` com domínio rico.
- [x] `RegisterUserCommand` e `RegisterUserHandler`.
- [x] `RegistrationController`.
- [x] Migrations Doctrine.
- [x] Testes de unidade, integração e HTTP do cadastro.
- [x] Data de nascimento write-once, conta incompleta e restrição a maiores de 18 (`ADR-044`).

### Fase 2 — Identity: autenticação

- [x] `user_identities` 1:N (local + Google) e CPF opcional/write-once.
- [x] Entidade `RefreshToken` opaca, com família, rotação e detecção de reuso.
- [x] `LcobucciAccessTokenGenerator` e `OpaqueRefreshTokenGenerator`.
- [x] `TokenPairIssuer` com emissão, rotação, revogação e limite de famílias.
- [x] `AuthController` com login, social, refresh, logout e logout/all.
- [x] `MeController` com `/users/me` e `/users/me/cpf`.
- [x] Adapter de segurança (`SecurityUser`, `SecurityUserProvider`, `ActiveUserChecker`).
- [x] Autenticador de access token Bearer.
- [x] Google via authorization code + state/PKCE + JWKS.
- [x] E-mail canônico único e vínculo explícito de identidade social.
- [x] Verificação de e-mail e recuperação/troca de senha.
- [x] Rate limiting de cadastro, login, social, e-mail, senha, refresh e CPF.
- [x] Testes de unidade, integração e funcionais dos fluxos principais, incluindo sucesso do login Google com provedor
  fake.
- [x] Página `/dev/google-oauth` para diagnóstico local sem Playground público
  (<https://localhost:8443/dev/google-oauth>, só em `APP_ENV=dev`).

### Fase 3 — Identity: onboarding

- [x] Regras de onboarding no agregado `User`.
- [x] Value objects de altura, peso, modalidades e status de onboarding.
- [x] `CompleteOnboardingCommand` e `CompleteOnboardingHandler`.
- [x] `OnboardingController` e DTOs de request/response.
- [x] Publicação efetiva de `OnboardingCompleted`.
- [x] Teste funcional do fluxo registro → onboarding → nível 2.
- [ ] Consumo de `OnboardingCompleted` pela Gamification (Fase 6).

### Fase 3.1 — Perfil e foto

- [x] `FileStorageInterface`/`FlysystemFileStorage` e configuração S3/Garage.
- [x] `PATCH /users/me/profile` com semântica de JSON Merge Patch.
- [x] `POST /users/me/photo` (multipart) e `DELETE /users/me/photo` idempotente.
- [x] Onboarding restrito a modalidades e modalidade principal.
- [x] Migration de `photo_url` para `photo_key` e limpeza dos valores antigos.
- [x] Testes unitários, funcionais e de integração do perfil e da foto.

### Fase 4 — Groups

- [x] Entidades `Group` e `Membership`, com episódios append-only e estados `PENDING`, `APPROVED`, `REJECTED` e
  `REMOVED`.
- [x] Campos do grupo: nome, descrição, modalidade e link externo de comunicação.
- [x] Criador registrado em `createdBy`, independente do papel de administrador.
- [x] Responsável (`ownerId`) transferível, sempre um admin aprovado; exclusão e transferência exclusivas dele.
  O responsável não pode ser rebaixado, removido nem sair sem transferir; não há sucessão automática na V1.
- [x] Múltiplos administradores, com proteção do último admin.
- [x] Casos de uso `CreateGroup`, `UpdateGroup`, `RequestMembership`, `ApproveMembership`, `RejectMembership`,
  `RemoveMember`, `ChangeMembershipRole`, `TransferOwnership`, `DesignateSupervisor`, `ClearSupervisor`, `ListGroups`,
  `ListMyGroups`, `GetGroup`, `ListMemberships`, `LeaveGroup`, `ArchiveGroup`, `UnarchiveGroup` e `DeleteGroup`.
- [x] Limite de 5 participações por usuário, 1 grupo em formação por responsável, dedupe por nome/modalidade, ativação
  persistida em `activated_at` e limite de membros configurável por grupo (ilimitado quando ausente).
- [x] Arquivamento reversível, exclusão lógica terminal, saída do membro e arquivamento automático de grupos em
  formação inativos há 30 dias (`scheduler_groups`).
- [x] Autorização por administrador do grupo e supervisor sem poderes de gestão de membros.
- [x] Endpoints HTTP, rate limiting e documentação OpenAPI na tag `Grupos`.
- [x] Migration `Version20261109120000` com índices parciais de duplicidade e supervisor.
- [x] Migration `Version20261110183601` com `groups.max_members` (null = ilimitado).
- [x] Migration `Version20261112160821` com `activated_at`, campos de arquivamento/exclusão e índices de inatividade e
  dedupe.
- [x] Migration `Version20261112191934` com `owner_id`, índice de dedupe por responsável e histórico
  `group_ownership_transfers`.
- [x] Notificações de arquivamento e exclusão, incluindo e-mail para o responsável no arquivamento; transferência
  notifica antigo e novo responsável (e-mail só para o novo).
- [x] Testes de domínio, handlers, repositório e HTTP.

### Fase 5 — Activities

#### 5.1 Fundação e integração

- [x] Porta `Shared\Application\Group\GroupAccessReaderInterface` e adapter `DoctrineGroupAccessReader`.
- [x] VOs, enums, `ActivityPolicy` e catálogo `ActivityErrorCode`/`ActivityErrors`.
- [x] Entidades `Activity`, `Attendance` e `ActivityReschedule`.
- [x] Mapping Doctrine, tipos DBAL e migration `Version20261109224939`.
- [x] Correção dos índices parciais de `group_memberships` para os valores reais dos enums (`Version20261109230000`).

#### 5.2 Criação e consulta

- [x] `CreateActivity`, `GetActivity`, `ListGroupActivities` e `ListMyActivities`.
- [x] Endpoints, tag OpenAPI `Atividades`, rate limits e respostas.
- [x] Visibilidade restrita a membros aprovados, com bypass para admin SIGAME.

#### 5.3 Ciclo de vida e histórico

- [x] `UpdateActivity` com remarcação e histórico append-only.
- [x] `CancelActivity`, `StartActivity`, `FinishActivity` e `InvalidateActivity`.
- [x] Janela de início, finalização e invalidação.
- [x] Eventos de criação, alteração, remarcação, cancelamento, início, finalização e invalidação.

#### 5.4 Inscrição e conflito

- [x] `EnrollInActivity` e `CancelEnrollment`.
- [x] Conflito sem margem, com lock por atividade/usuário.
- [x] Remarcação recusada quando cria conflito para inscrito ativo.
- [x] Índice único parcial de inscrição ativa.

#### 5.5 Check-in e presença

- [x] `CheckIn`, `ValidateAttendance` e `ListAttendances`.
- [x] Idempotência de inscrição, check-in e validação.
- [x] `xpEligible` com mínimo de 2 check-ins.
- [x] Evento `AttendanceValidated` com `xpEligible` e evento de invalidação com usuários para reversão.

#### 5.6 Fechamento automático

- [x] `symfony/scheduler` e `ActivityClosingSchedule` a cada 5 minutos.
- [x] `CloseExpiredActivitiesMessage` e `CloseExpiredActivitiesHandler`.
- [x] Tolerância proporcional (`50%`, mínimo 15 min, máximo 240 min) e fallback de 12 horas.
- [x] Cancelamento automático de atividade não iniciada após 3 horas.
- [x] Serviço `scheduler` no Compose e receitas `just scheduler`/`just scheduler-logs`.

#### 5.7 Testes e qualidade

- [x] Testes de domínio, policy, handler de fechamento, repositório e HTTP.
- [x] Executar `just composer quality` no fechamento da fase.

### Fase 6 — Gamification

A especificação detalhada de XP, ofensiva semanal e deleveling está em
[requisitos/levantamento-de-requisitos.md](requisitos/levantamento-de-requisitos.md#rf12--progressão-de-xp-e-níveis).
As decisões estão nos ADRs 032–036. A fase **não possui ofensiva diária**: a única constância incentivada é a
semanal.

#### 6.1 Fundação e regras

- [x] Criar o módulo `Gamification` com `Domain`, `Application`, `Infrastructure` e `Presentation`.
- [x] `GamificationRules` versionada (`2026-11-v1`): curva, XP de atividade, bônus semanais, grupo, fim de semana, fator
  diário, teto e fuso.
- [x] `LevelCurve`, `ActivityXpPolicy`, `WeeklyXpPolicy`, `StreakCalculator` e `DelevelingPolicy`.
- [x] `UserProgress`, `XpEvent` (ledger append-only) e `PlayerId`.
- [x] Migration de `user_progress` e `xp_events`, com índices e `idempotency_key` único; bônus semanais reawardados usam
  a atividade como sufixo da chave.
- [x] Backfill a partir de `users.level` e do histórico de presenças.
- [x] Registrar mapping e tipos DBAL em `config/packages/doctrine.php`.
- [x] Catálogo de erros: a fase não expõe erros de domínio públicos; os endpoints de leitura não retornam erros de
  negócio.

#### 6.2 Registro e onboarding

- [x] Listener `UserRegistered → CreateProgress` (0 XP, nível 1).
- [x] Listener `OnboardingCompleted → GrantOnboardingXp` (+100 XP, idempotente).
- [x] Remover `level->next()` de `User::completeOnboarding()`.
- [x] Adicionar `User::syncLevel()` e listener `SyncUserLevel` em Identity.
- [x] Ajustar testes de domínio, handler e funcionais do onboarding.
- [x] Garantir que a resposta do onboarding já retorne nível 2.

#### 6.3 XP por atividade

- [x] Porta `ActivityXpReaderInterface` + `ActivityXpContext` + adapter em Activities.
- [x] Porta de modalidade do grupo.
- [x] Listener `AttendanceValidated → AwardActivityXp` (só `xpEligible=true`).
- [x] Fórmula de base, distância, duração, grupo e fim de semana.
- [x] Fator diário: 1ª–3ª atividade a 100%, 4ª a 25%, 5ª em diante a 0.
- [x] Teto de 100 XP por atividade.
- [x] Ledger com origem, data da atividade, semana e versão da regra.
- [x] Snapshot em `metadata`: modalidade, distância, duração, check-ins, sequência diária e valor calculado.
- [x] Publicação de `XpAwarded`, `LevelReached` e `LevelLost`.
- [x] Testes de tabela, idempotência e limites.

#### 6.4 Ofensiva semanal

- [x] `StreakCalculator` baseado no ledger.
- [x] `WEEKLY_START` (+10) na primeira atividade da semana.
- [x] `WEEKLY_GOAL` (20 a 50 XP conforme a sequência) na segunda atividade.
- [x] 1 atividade mantém o foguinho; 0 apaga; recorde preservado.
- [x] `ActivityDate` e `WeekKey` no fuso `America/Sao_Paulo`.
- [x] Recalcular após validação tardia e estorno.
- [x] Snapshot em `metadata`: `weeklyCount`, `streak` e valor calculado.
- [x] Publicar `WeeklyGoalCompleted` e `StreakUpdated`.
- [x] **Não implementar ofensiva diária nem bônus de primeira atividade do dia.**

#### 6.5 Deleveling

- [x] `DelevelingPolicy`: 7 dias de tolerância, 2% a cada 2 dias, 98% ao cair, piso 2.
- [x] `ApplyInactivityDecayMessage` + handler + schedule diário.
- [x] Idempotência por `userId + periodEnd`.
- [x] XP vitalício preservado.
- [x] Publicar `LevelLost` e `XpDecayed`.
- [x] Testes de datas, múltiplos períodos, piso e idempotência.

#### 6.6 Estorno por invalidação

- [x] Listener `ActivityInvalidated → ReverseActivityXp`.
- [x] Estornar atividade e bônus semanais vinculados.
- [x] Recalcular nível e ofensiva.
- [x] Unique de estorno por lançamento.
- [x] Testes de estorno único e de queda de nível.

#### 6.7 API e consulta

- [x] `GET /api/v1/users/me/progress`.
- [x] `GET /api/v1/users/me/progress/history`.
- [x] Response com nível, saldo, XP vitalício, barra, foguinho, meta semanal e bônus aplicáveis.
- [x] Tag OpenAPI `Gamificação` e `x-tagGroups`.
- [x] Rate limit de leitura.
- [x] Testes funcionais de autenticação, payload e isolamento por usuário.

#### 6.8 Versionamento e recálculo

- [x] `rule_version` em `user_progress` e `xp_events`.
- [ ] Comando `app:gamification:recalculate`.
- [ ] `LEGACY_BONUS` para não reduzir XP antigo.
- [ ] Teste de mudança de regra.
- [ ] Documentar o procedimento.

#### 6.9 Qualidade

- [x] Executar `just composer quality` no fechamento da fase (914 testes verdes em 2026-11-10).

#### 6.10 Dívida assumida — `users.level`

O nível continua em `users.level` porque Groups e a segurança já dependem dele. A fonte de verdade é
`user_progress.level`, e `users.level` é uma projeção atualizada por `LevelReached`/`LevelLost`. O comando
`app:gamification:check-level-projection` compara as duas fontes e reporta divergências; com `--repair`, corrige
a projeção. A dívida está detalhada no [ADR-036](decisoes.md#adr-036--nível-como-projeção-de-identity) e na
[tabela de dívidas](#5-dívidas-técnicas-e-próximos-passos).

#### 6.11 Integridade, auditoria e reparo

- [x] Porta `UserLevelProjectionInterface` com adapter em Identity para reparo explícito da projeção.
- [x] `UserProgress::reconcile()` para alinhar saldo e XP vitalício ao ledger.
- [x] `app:gamification:audit-ledger` (somente leitura por padrão, com `--user-id` e `--repair`).
- [x] Auditoria de saldo, XP vitalício, nível materializado e projeção `users.level`.
- [x] Auditoria de integridade referencial: reversal sem original e original revertido sem reversal.
- [x] Snapshot de modalidade, distância, duração, check-ins, sequência diária e valor calculado no `metadata` do
  `XpEvent`.
- [x] Snapshot de `weeklyCount`, `streak` e valor calculado nos bônus semanais.
- [x] `GET /users/me/progress` deixa de escrever no banco a cada leitura; o progresso só é persistido quando criado.
- [x] Testes de consistência após onboarding, validação, estorno e decay.
- [x] Testes de auditoria, reparo, criação de progresso ausente e projeção divergente.
- [x] Teste de idempotência do reparo e do `--repair` sem flag.

#### 6.12 Catálogo de regras

- [x] `ActivityXpTable` como fonte única das faixas de XP de atividade.
- [x] `GET /api/v1/gamification/rules` com curva de níveis, fontes de XP, fórmula, bônus semanais, decay e estados do
  foguinho.
- [x] Payload gerado do domínio, com `ruleVersion`, `ETag` e cache privado.
- [x] Rótulos PT-BR como hint de exibição, reutilizando `XpKind::label()` e `WeeklyStreakState::label()`.
- [x] Testes de unidade, funcionais, de cache e de documentação.

#### 6.13 Simulador de XP (V1.1)

- [ ] `POST /api/v1/gamification/xp-simulator` autenticado e sem persistência.
- [ ] Entrada: modalidade, distância, duração, check-ins, início e sequência diária.
- [ ] Saída: componentes do cálculo, total e explicação.
- [ ] Reutilizar `ActivityXpPolicy`/`ActivityXpTable`, extraídos em 6.12.
- [ ] Exibir "até 100 XP" na V1 enquanto o simulador não existe.

### Fase 7 — Notificações + Administração de contas

A especificação detalhada do catálogo, canais e regras está em
[requisitos/levantamento-de-requisitos.md](requisitos/levantamento-de-requisitos.md#rf15--notificar-eventos-relevantes)
e [RF18](requisitos/levantamento-de-requisitos.md#rf18--suspender-e-reativar-conta-globalmente).

#### 7.1 Fundação de Notifications

- [x] Criar o módulo `Notifications` com `Domain`, `Application`, `Infrastructure` e `Presentation`.
- [x] `Notification` e `NotificationDelivery` com tipo, categoria, título, corpo, payload, leitura e idempotência.
- [x] VOs de tipo, categoria, canal, status de entrega, título, corpo e chave de deduplicação.
- [x] Repositórios Doctrine, tipos DBAL e migration `Version20261110145733`.
- [x] Portas `RealtimePublisherInterface`, `StreamAuthorizationProviderInterface` e `NotificationChannelInterface`.

#### 7.2 Público e conteúdo

- [x] Portas `UserContactReaderInterface`, `UserActiveReaderInterface`, `GroupSummaryReaderInterface` e
  `ActivitySummaryReaderInterface` no `Shared`.
- [x] Adapters em Identity, Groups e Activities.
- [x] `NotificationAudienceResolver`, `NotificationContentFactory`, `NotificationPlanner` e `NotificationChannelPolicy`.
- [x] Catálogo de 21 tipos aprovado, com exclusão do ator e filtro de suspensos.

#### 7.3 Pipeline assíncrono

- [x] Listeners de grupos, atividades, gamificação e conta publicando `CreateNotificationsMessage`.
- [x] `CreateNotificationsHandler` idempotente, com resolução de público no worker.
- [x] `DeliverNotificationMessage` e handler de entrega com tentativas e status.
- [x] Falha de notificação não reverte a operação principal.
- [x] Roteamento de `CreateNotificationsMessage` e `DeliverNotificationMessage` para `async`.

#### 7.4 Canal e-mail

- [x] `EmailNotificationChannel` e template `email/notification.html.twig`.
- [x] E-mail apenas para `ACCOUNT_SUSPENDED` e `ACCOUNT_REACTIVATED`.
- [x] Entrega registrada em `notification_deliveries` com `SENT`, `FAILED` ou `SKIPPED`.

#### 7.5 API, leitura e Mercure

- [x] `GET /api/v1/notifications`, `/unread-count`, `/{id}/read`, `/read-all` e `/realtime`.
- [x] Filtro `all|unread|read`, categoria e paginação; frontend pode ocultar lidas.
- [x] Marcar uma e marcar todas idempotentes, isoladas por destinatário.
- [x] Mercure com tópico privado por usuário e token de assinatura de curta duração, somente leitura.
- [x] Publicação de `notification.created`, `notification.read` e `notifications.read_all`.
- [x] Configuração do bundle, protocolo legado 0.x, envs e CORS do hub.

#### 7.6 Administração de contas

- [x] `UserSuspension` append-only, `UserSuspended` e `UserReactivated`.
- [x] `User::suspend()`/`reactivate()` e `grantAdminRole()`/`revokeAdminRole()`.
- [x] `SuspendUser`, `ReactivateUser`, `ListUsers`, `GetUser` e `ListStatusHistory`.
- [x] Revogação de todas as sessões na suspensão.
- [x] Endpoints `/api/v1/admin/users...`, tag `Usuários`, grupo `Administração`.
- [x] Comandos `app:identity:grant-admin` e `app:identity:revoke-admin`.
- [x] `ApproveMembershipHandler` recusa aprovação de usuário suspenso.
- [x] Migration `Version20261110150000` amplia `users.role` para suportar `ADMIN_SIGAME`.

#### 7.7 Testes e documentação

- [x] Testes unitários de notificação, planejador, canais, Mercure e suspensão.
- [x] Testes funcionais de inbox, leitura, atividade, administração e e-mail.
- [x] Teste de integração do repositório de notificações.
- [x] `ApiDocumentationTest` atualizado com tags, rotas e summaries.
- [x] Fechar `just composer quality` e atualizar os documentos correlatos (suíte completa verde em 2026-11-10).

### Fase 8 — Sponsorship + Reports

- [ ] Patrocínios, benefícios e vouchers.
- [ ] Indicadores mensais de desempenho dos grupos.

---

## 3. Decisões em aberto

As decisões abaixo ainda precisam ser fechadas antes da implementação do módulo correspondente. Quando uma decisão for
tomada, mova o registro para [decisoes.md](decisoes.md) e atualize o requisito relacionado.

| ID      | Questão                                     | Impacto     | Prioridade | Status   |
|---------|---------------------------------------------|-------------|------------|----------|
| `DEC06` | Matriz final de visibilidade de dados       | Privacidade | Alta       | Pendente |
| `DEC07` | Público, distribuição e limites de vouchers | Vouchers    | Alta       | Pendente |
| `DEC08` | Fórmula e periodicidade do indicador mensal | Indicadores | Média      | Pendente |
| `DEC15` | Transferência de responsabilidade do grupo  | Grupos      | Baixa      | Fechada (ADR-046) |
| `DEC16` | Validação própria e elegibilidade de XP     | Gamificação | Média      | Pendente |

Adiado para a V1.1: o simulador de XP (`POST /api/v1/gamification/xp-simulator`) descrito na subseção 6.13 do roadmap.
Na V1, o catálogo de regras permite exibir a faixa de XP e o histórico entrega o valor real concedido.

O planejamento de uso por menores que **não** entrou na V1 (faixas de idade, consentimento do responsável, exposição
mínima, acompanhantes em atividades e expurgo de contas incompletas) está arquivado em
[controle-parental.md](controle-parental.md).

Decisões fechadas na Fase 5:

| ID      | Decisão                                               | ADR                                                                                  |
|---------|-------------------------------------------------------|--------------------------------------------------------------------------------------|
| `DEC01` | Conflito sem margem e sem override                    | [ADR-024](decisoes.md#adr-024--conflito-de-horário-sem-margem-e-sem-exceção)         |
| `DEC10` | Janela operacional de atividade, check-in e validação | [ADR-025](decisoes.md#adr-025--janela-operacional-da-atividade-check-in-e-validação) |
| `DEC11` | Supervisor como operador da atividade, sem gestão     | [ADR-027](decisoes.md#adr-027--poderes-de-atividade-do-supervisor)                   |
| `DEC12` | Atividade válida e mínimo de 2 check-ins              | [ADR-026](decisoes.md#adr-026--atividade-válida-para-xp-e-mínimo-de-participantes)   |

Decisões fechadas na Fase 6:

| ID      | Decisão                                                                            | ADR                                                                                                                                                                                                                            |
|---------|------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `DEC02` | Curva de níveis, XP por atividade, bônus de grupo/fim de semana e ofensiva semanal | [ADR-032](decisoes.md#adr-032--modelo-de-xp-em-duas-camadas-e-ledger-append-only), [ADR-033](decisoes.md#adr-033--curva-de-níveis-e-xp-por-atividade) e [ADR-034](decisoes.md#adr-034--ofensiva-semanal-e-bônus-de-constância) |
| `DEC03` | Fórmula exata do deleveling                                                        | [ADR-035](decisoes.md#adr-035--deleveling-e-piso-no-nível-2)                                                                                                                                                                   |

Decisões fechadas na Fase 7:

| ID      | Decisão                                                            | ADR                                                                                |
|---------|--------------------------------------------------------------------|------------------------------------------------------------------------------------|
| `DEC04` | Notificações multicanal, inbox, leitura e Mercure                  | [ADR-037](decisoes.md#adr-037--notificações-multicanal-com-inbox-canais-e-mercure) |
| `DEC05` | Denúncias e bloqueios adiados; administração de contas em Identity | [ADR-038](decisoes.md#adr-038--denúncias-e-bloqueios-fora-da-v1)                   |
| —       | Suspensão global: sessões, vínculos e histórico                    | [ADR-039](decisoes.md#adr-039--suspensão-global-de-contas)                         |
| —       | Mercure como transporte em tempo real do sino                      | [ADR-040](decisoes.md#adr-040--mercure-como-transporte-em-tempo-real-do-sino)      |

Decisões técnicas da Fase 5:

- [ADR-028](decisoes.md#adr-028--modelo-de-activities-janela-inscrição-presença-e-histórico) — modelo de Activities;
- [ADR-029](decisoes.md#adr-029--consulta-síncrona-de-grupo-por-activities) — porta síncrona de grupo;
- [ADR-030](decisoes.md#adr-030--importação-de-eventos-entre-módulos) — importação de eventos entre módulos;
- [ADR-031](decisoes.md#adr-031--duração-opcional-e-fechamento-automático-de-atividades) — duração opcional e fechamento
  automático.

As demais decisões já fechadas (`DEC09`, `DEC13` e `DEC14`) e os ADRs arquiteturais estão em [decisoes.md](decisoes.md).

### Decisões técnicas em aberto

| Tema                      | O que falta definir                                                                                                                                             |
|---------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Rate limiter em produção  | Com mais de uma instância, o pool `cache.rate_limiter` precisa apontar para armazenamento compartilhado (Redis ou banco). Hoje o padrão é o cache da aplicação. |
| Limpeza de refresh tokens | Não há rotina para remover tokens expirados ou revogados antigos.                                                                                               |
| Processamento de imagem   | Não há GD/Imagick: falta resize/thumbnail, leitura de EXIF e detecção de imagem corrompida no servidor.                                                         |

---

## 4. Migrações do banco de dados

O schema evoluiu por migrations incrementais. Todas estão em `migrations/` e são aplicadas automaticamente pelo
entrypoint do container.

| Migration               | Data       | O que mudou                                                                                                                    |
|-------------------------|------------|--------------------------------------------------------------------------------------------------------------------------------|
| `Version20260921225624` | 2026-09-21 | Cria `users` com CPF/e-mail obrigatórios, senha, perfil em JSON, papel, nível, status e onboarding.                            |
| `Version20261029173517` | 2026-10-29 | Troca `created_at`/`updated_at` para `TIMESTAMP(6) WITH TIME ZONE`, preservando microssegundos e timezone.                     |
| `Version20261029191722` | 2026-10-29 | Substitui `users.profile` JSON por `height_cm`, `weight_kg` e `photo_url`; cria `user_modalities` e migra os dados existentes. |
| `Version20261104120000` | 2026-11-04 | Cria `user_identities` e `refresh_tokens`; move e-mail/senha para identidades; torna `users.cpf` opcional.                     |
| `Version20261105153437` | 2026-11-05 | Torna `users.updated_at` obrigatório, preenchendo nulos com `created_at`.                                                      |
| `Version20261106090000` | 2026-11-06 | Adiciona `users.email` e `users.email_verified_at` e faz o backfill a partir das identidades.                                  |
| `Version20261106090100` | 2026-11-06 | Valida duplicatas e cria o índice único de `users.email`.                                                                      |
| `Version20261106090200` | 2026-11-06 | Cria `identity_tokens` para verificação de e-mail e reset de senha.                                                            |
| `Version20261106100000` | 2026-11-06 | Cria `messenger_messages` para o transporte Doctrine do Messenger.                                                             |
| `Version20261108120000` | 2026-11-08 | Renomeia `users.photo_url` para `photo_key`, limpa URLs antigas e reduz a coluna para 255 caracteres.                          |
| `Version20261109120000` | 2026-11-09 | Cria `groups` (com `created_by`) e `group_memberships` (episódios, índices parciais de duplicidade e supervisor).              |
| `Version20261109224939` | 2026-11-09 | Cria `activities`, `activity_attendances` e `activity_reschedules`; índice único parcial de inscrição ativa.                   |
| `Version20261109230000` | 2026-11-09 | Corrige os índices parciais de `group_memberships` para os valores minúsculos dos enums.                                       |
| `Version20261110000000` | 2026-11-10 | Cria `user_progress` e `xp_events`; backfill a partir de `users.level` e das presenças validadas.                              |
| `Version20261110145733` | 2026-11-10 | Cria `notifications`, `notification_deliveries` e `user_suspensions`; índices de inbox e histórico.                            |
| `Version20261110150000` | 2026-11-10 | Amplia `users.role` para 20 caracteres para suportar `ADMIN_SIGAME`.                                                           |
| `Version20261110183601` | 2026-11-10 | Adiciona `groups.max_members` (nulo = grupo ilimitado).                                                                        |
| `Version20261111120000` | 2026-11-11 | Adiciona `users.birth_date DATE NULL`, preenchida pelo endpoint write-once e exigida no onboarding.                            |
| `Version20261112160821` | 2026-11-12 | Adiciona `activated_at`, campos de arquivamento e exclusão em `groups`; backfill e índices de inatividade e dedupe.           |

A edição parcial de perfil não exigiu migration: `height_cm`, `weight_kg` e `user_modalities` já existiam. A foto passou
de URL para chave de objeto em `Version20261108120000`.

A Fase 6 criará a migration de `user_progress` e `xp_events`; o nome será gerado por
`just sf make:migration` e registrado nesta tabela quando a fase for implementada.

### Regras

1. Toda alteração de schema passa por `bin/console make:migration` e `doctrine:migrations:migrate`. Nunca use
   `doctrine:schema:update`.
2. O ambiente de teste usa `zenstruck_foundry.orm.reset.mode: migrate`; o schema vem das migrations, não do mapping.
3. Cada teste de integração/funcional roda dentro de uma transação com rollback (DAMA). Não abra transações explícitas,
   não use `TRUNCATE` e não chame `commit()`/`rollBack()` dentro do teste.
4. A migration `Version20261104120000` é **parcialmente irreversível**: usuários sem CPF ou sem identidade local impedem
   restaurar as colunas `NOT NULL` originais. O `down()` deve ser tratado como melhor esforço.
5. Ao adicionar um novo tipo Doctrine, registre-o em `config/packages/doctrine.php` e crie o teste do tipo.
6. `doctrine:schema:validate` nunca fica verde por causa dos três índices parciais: é falso positivo do DBAL (ver §5) e
   não deve gerar migration. Use `doctrine:schema:update --dump-sql` para conferir divergências reais; o teste
   `SchemaDriftTest` cobre o restante.

---

## 5. Dívidas técnicas e próximos passos

As dívidas adicionais identificadas na revisão da Fase 6 estão em [§5.2](#52-dívidas-adicionais-da-gamificação).

| Item                                              | Evidência                                                                                                                                                                                                                                                                                              | Ação sugerida                                                                                                                                                                                                                                                               |
|---------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Troca de modalidade principal ausente             | RN03.5/CA03.5 exigem alterar a principal preservando histórico, mas `completeOnboarding()` só aceita uma conclusão                                                                                                                                                                                     | Criar caso de uso e endpoint próprios, com teste de preservação de XP.                                                                                                                                                                                                      |
| Imagem sem pós-processamento                      | O endpoint só valida e armazena; o enquadramento em 512×512 é feito pelo cliente e o arquivo original é persistido                                                                                                                                                                                     | Ver [§5.1](#51-processamento-de-imagem).                                                                                                                                                                                                                                    |
| Objetos órfãos em falha abrupta                   | A compensação cobre falha de persistência, mas um processo morto entre o upload e o save deixa o objeto no bucket                                                                                                                                                                                      | Criar rotina de limpeza por referência/idade ou lifecycle policy no provedor.                                                                                                                                                                                               |
| Leitura pública da foto                           | O bucket local usa website público com chave não adivinhável; a matriz definitiva do `DEC06` segue aberta                                                                                                                                                                                              | Reavaliar quando a visibilidade de perfil for fechada; a chave persistida permite migrar para proxy ou URL assinada sem migration.                                                                                                                                          |
| CI não roda testes                                | `.github/workflows/ci.yaml` tem os passos de migrations/PHPUnit comentados                                                                                                                                                                                                                             | Reativar migrations, testes e `doctrine:schema:validate` no CI.                                                                                                                                                                                                             |
| `doctrine:schema:validate` acusa índices parciais | O DBAL compara o texto do `WHERE` do mapping com o `pg_get_expr` do PostgreSQL, que reescreve `IN` como `= ANY (ARRAY[...])` e `TRUE` como `true`; por isso `uniq_group_memberships_active`, `uniq_group_memberships_supervisor` e `uniq_activity_attendances_active` aparecem sempre como divergência | Tratar como falso positivo: conferir o diff com `doctrine:schema:update --dump-sql` e nunca gerar migration a partir dele. O teste `SchemaDriftTest` falha se aparecer divergência nova; só reativar o `schema:validate` no CI com um comparador que normalize o predicado. |
| Refresh tokens e tokens de identidade acumulam    | Não há comando de limpeza                                                                                                                                                                                                                                                                              | Criar comando agendado para remover tokens expirados/consumidos.                                                                                                                                                                                                            |
| Cache de fluxo OAuth por instância                | `cache.social_flow` usa o cache local                                                                                                                                                                                                                                                                  | Em múltiplas instâncias, apontar `cache.social_flow` e `cache.rate_limiter` para Redis/banco.                                                                                                                                                                               |
| CRUD administrativo completo ausente              | A Fase 7 entregou listagem, busca, detalhe, suspensão e reativação; não há edição/exclusão de contas                                                                                                                                                                                                   | Implementar os demais fluxos quando o produto exigir.                                                                                                                                                                                                                       |
| Schedulers dependem do serviço                    | Se o serviço `scheduler` estiver parado, o fechamento automático de atividades e o deleveling atrasam até a próxima execução                                                                                                                                                                           | Monitorar o serviço e documentar a operação em produção.                                                                                                                                                                                                                    |
| Sem fechamento automático de validação            | A janela de validação é validada na requisição; não há job para encerrar pendências de validação                                                                                                                                                                                                       | Avaliar rotina de fechamento após 48 h.                                                                                                                                                                                                                                     |
| Busca de grupos com `LIKE`                        | `DoctrineGroupRepository::search()` usa `LOWER(name) LIKE`                                                                                                                                                                                                                                             | Avaliar índice textual ou busca por trigramas se o volume crescer.                                                                                                                                                                                                          |
| Sem exclusão física de grupo                       | A exclusão é lógica (`deleted_at`) e o responsável não transfere dados históricos; o admin não responsável não exclui                                                                                                                                                                                | Manter a exclusão lógica; a responsabilidade é transferível pelo ADR-046.                                                                                                                                                                                                   |
| **Nível com dupla fonte (mitigada)**              | `users.level` continua sendo a projeção usada por Groups e segurança; a fonte de verdade é `user_progress.level`. A consistência agora é coberta por testes e pelos comandos `app:gamification:check-level-projection --repair` e `app:gamification:audit-ledger --repair`                             | A dupla fonte permanece por decisão do [ADR-036](decisoes.md#adr-036--nível-como-projeção-de-identity); reavaliar apenas se houver necessidade de XP em Identity ou regras dinâmicas.                                                                                       |
| **Comando de recálculo pendente**                 | `rule_version` já é gravado, mas não existe `app:gamification:recalculate` nem `LEGACY_BONUS`                                                                                                                                                                                                          | Implementar o replay do XP de atividade e o bônus de fidelidade antes de mudar a versão das regras.                                                                                                                                                                         |
| **Bônus de grupo por check-in**                   | O bônus usa check-ins para que todos os participantes recebam o mesmo valor, mas um check-in não validado ainda pode influenciar o bônus                                                                                                                                                               | Avaliar cálculo em lote na finalização ou reajuste após a validação de todos.                                                                                                                                                                                               |
| **Sem escudo/freeze**                             | Uma semana atípica apaga a ofensiva semanal, mesmo com histórico consistente                                                                                                                                                                                                                           | Implementar na V1.1: 1 escudo a cada 4 semanas, máximo 2, consumido automaticamente.                                                                                                                                                                                        |
| **Recálculo retroativo de engajamento**           | O comando de recálculo da Fase 6 cobre XP de atividade; bônus semanais antigos não são recalculados                                                                                                                                                                                                    | Fazer replay completo do ledger em uma versão futura, se as regras semanais mudarem.                                                                                                                                                                                        |
| **Ofensiva de grupo**                             | O foguinho é individual; não existe ofensiva coletiva do grupo                                                                                                                                                                                                                                         | Avaliar na V1.1 ou na Fase 8, junto com os indicadores mensais.                                                                                                                                                                                                             |
| **XP em lote na finalização**                     | O XP é concedido na validação individual e o bônus de grupo usa check-ins                                                                                                                                                                                                                              | Avaliar consolidar o XP na finalização da atividade para usar apenas presenças validadas.                                                                                                                                                                                   |
| **Validação própria**                             | Admin/supervisor pode validar a própria presença; a regra atual não impede explicitamente                                                                                                                                                                                                              | Decidir se o XP deve exigir um validador diferente do participante (`DEC16`).                                                                                                                                                                                               |
| **Sem retenção de notificações**                  | `notifications` cresce indefinidamente; não há cleanup                                                                                                                                                                                                                                                 | Adicionar scheduler de retenção (ex.: 180 dias) quando o volume justificar.                                                                                                                                                                                                 |
| **WhatsApp e push não implementados**             | A V1 só entrega e-mail para eventos de conta                                                                                                                                                                                                                                                           | Coletar telefone/opt-in e device tokens antes de habilitar novos canais.                                                                                                                                                                                                    |
| **Sem preferências de notificação**               | A política de canais é fixa por tipo                                                                                                                                                                                                                                                                   | Criar `notification_preferences` por usuário/tipo/canal em versão futura.                                                                                                                                                                                                   |
| **Mercure é best-effort**                         | Falha de publicação não desfaz a notificação e a revogação do token ocorre por expiração                                                                                                                                                                                                               | Monitorar o hub e manter o fallback de consulta da API.                                                                                                                                                                                                                     |
| **Notificações dependem do worker**               | Criação e entrega são assíncronas                                                                                                                                                                                                                                                                      | Monitorar `async`; mensagens permanecem na fila se o worker parar.                                                                                                                                                                                                          |
| **Conteúdo PT-BR fixo**                           | Título e corpo são renderizados no backend sem i18n                                                                                                                                                                                                                                                    | Adicionar locale/preferência quando houver mais idiomas.                                                                                                                                                                                                                    |
| **Sem digest/agrupamento**                        | Cada evento gera um item; grupos pequenos limitam o ruído hoje                                                                                                                                                                                                                                         | Avaliar agrupamento se o volume por usuário crescer.                                                                                                                                                                                                                        |
| **Busca administrativa com LIKE**                 | `LOWER(name/email) LIKE` sem índice textual                                                                                                                                                                                                                                                            | Avaliar índice de trigramas se a base crescer.                                                                                                                                                                                                                              |
| **Responsável suspenso**                          | A suspensão não transfere o grupo; excluir e transferir ficam bloqueados até a reativação                                                                                                                                                                                                              | Intervenção manual da SIGAME; o banimento permanente (futuro) será o caminho automático.                                                                                                                                                                                    |
| **Motivo público vs. nota interna**               | O motivo é exibido ao titular e não há campo separado para anotação interna                                                                                                                                                                                                                            | Definir política de moderação antes de adicionar notas internas.                                                                                                                                                                                                            |
| **Protocolo Mercure legado**                      | O hub do Caddy usa `protocol_version_compatibility 8` (0.x)                                                                                                                                                                                                                                            | Migrar para 1.0 quando o hub e o bundle forem atualizados.                                                                                                                                                                                                                  |
| **CORS do hub**                                   | O hub SSE é servido pelo Caddy fora do NelmioCors                                                                                                                                                                                                                                                      | Configurar `cors_origins` no hub para a origem do frontend.                                                                                                                                                                                                                 |
| **Admin provisionado por CLI**                    | Não há endpoint para elevar privilégios                                                                                                                                                                                                                                                                | Manter `app:identity:grant-admin`/`revoke-admin` e documentar a operação.                                                                                                                                                                                                   |
| **Sem métricas de notificação**                   | Não há contadores de criação, entrega, falha ou lag                                                                                                                                                                                                                                                    | Adicionar logs estruturados/métricas no Monitoring.                                                                                                                                                                                                                         |

### 5.1 Processamento de imagem

O endpoint de foto apenas valida e armazena o arquivo recebido. O enquadramento em 512×512 é responsabilidade do cliente
([ADR-018](decisoes.md#adr-018--foto-de-perfil-quadrada-em-512-no-cliente)), e o servidor não reescreve o arquivo. Ficam
como dívida técnica do backend:

- [ ] Converter a imagem para **WebP**.
- [ ] Garantir o **crop 512×512** no servidor, sem depender do cliente.
- [ ] **Remover EXIF e demais metadados** (hoje um cliente fora do contrato ainda pode publicar GPS no bucket público).
- [ ] Aplicar **compressão** com qualidade controlada.
- [ ] Gerar **múltiplos tamanhos** (ex.: 512, 256, 64) para listas e rankings — ou adotar um serviço de
  redimensionamento on-the-fly (imgproxy, Thumbor, Cloudflare Images) na frente do bucket.

**Gatilhos para pagar a dívida:** primeira tela que lista avatares, primeiro cliente fora do controle, ou primeiro
relato de metadados vazando.

**Forma de implementação:** a 512 px o custo é baixo (buffer de ~1 MiB, ~30–60 ms por imagem), então o processamento
pode ser síncrono no próprio endpoint, sem passar pelo `worker`. A fila só volta a fazer sentido se o upload aceitar
originais grandes de novo ou se a geração de derivadas crescer em volume.

### 5.2 Dívidas adicionais da gamificação

Identificadas na revisão da Fase 6 depois do fechamento do núcleo. Não bloqueiam a V1 atual, mas devem ser endereçadas
antes de mudanças de regra, escala ou produção.

**Resolvidas em 2026-11-10:** snapshot do contexto de atividade e dos bônus semanais no `metadata`; leitura de progresso
sem escrita a cada GET; auditoria e reparo do ledger; verificação de integridade referencial do ledger; teste e reparo
da projeção `users.level`; catálogo de regras exposto em `GET /api/v1/gamification/rules` (`ActivityXpTable` e
`GetGamificationRulesHandler`).

| Item                                                    | Evidência                                                                                                                                                                                                                             | Ação sugerida                                                                                                                  |
|---------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------|
| **Contexto de eventos antigos não versionado**          | Eventos gravados a partir de 2026-11-10 guardam modalidade, distância, duração, check-ins, sequência diária e valor calculado em `metadata`; eventos anteriores permanecem sem snapshot                                               | Usar o snapshot no recálculo futuro; para eventos antigos, consultar `ActivityXpReaderInterface` como aproximação.             |
| **Cálculo de ofensiva O(n) sobre o ledger**             | `StreakCalculator` e `findActiveActivityDates` percorrem todos os eventos ativos a cada validação e a cada leitura de progresso                                                                                                       | Materializar contadores e/ou cachear a lista de datas; recalcular do ledger apenas em auditoria e estorno.                     |
| **Ledger sem retenção ou particionamento**              | `xp_events` cresce indefinidamente; não há arquivamento, partição ou TTL                                                                                                                                                              | Particionar por ano ou arquivar eventos antigos, mantendo os agregados em `user_progress` e um histórico resumido.             |
| **Sem FKs para `users` e para `reversal_of_id`**        | As FKs foram removidas para manter o `doctrine:schema:validate` alinhado ao mapping; o `app:gamification:audit-ledger` agora reporta reversal sem original e original revertido sem reversal, mas o banco continua sem impedir órfãos | Reintroduzir as FKs via migration (com mapeamento de relação ou `schema_filter`) ou manter a validação periódica no comando.   |
| **Sem CHECK constraints em saldo, lifetime e nível**    | O domínio clampa, mas o banco aceita `xp_balance < 0`, `xp_lifetime < 0` e `level` fora de 1–10                                                                                                                                       | Adicionar `CHECK` constraints na migration.                                                                                    |
| **Decay sem horário fixo**                              | `InactivityDecaySchedule` usa `RecurringMessage::every('1 day')`, que conta a partir do start do worker, não às 03:00 de `America/Sao_Paulo`                                                                                          | Usar `RecurringMessage::cron('0 3 * * *', ...)` com timezone ou um `Trigger` diário no horário local.                          |
| **Dois schedulers no mesmo worker**                     | O serviço `scheduler` consome `scheduler_activities` e `scheduler_gamification` no mesmo processo; um handler lento atrasa o outro                                                                                                    | Separar em dois serviços ou workers dedicados quando o volume crescer.                                                         |
| **XP depende de distância e duração autodeclaradas**    | `estimatedDistanceMeters` e `estimatedDurationMinutes` são informados pelo usuário; não há GPS nem validação independente                                                                                                             | Manter na V1, mas prever limites por modalidade, auditoria de atividades suspeitas e integração futura com GPS/Strava.         |
| **Sem endpoints administrativos de gamificação**        | Não há como um admin SIGAME consultar o progresso de um usuário, ajustar XP manualmente ou disparar um recálculo                                                                                                                      | Criar endpoints administrativos com auditoria para leitura, ajuste manual (`XP_ADJUSTMENT`) e recálculo.                       |
| **Histórico de XP com paginação por offset**            | `findHistory` usa `setFirstResult`/`setMaxResults`; páginas profundas ficam lentas                                                                                                                                                    | Migrar para cursor por `(created_at, id)` e expor `nextCursor` na resposta.                                                    |
| **Sem índice composto para bônus semanal**              | `findActiveWeeklyEvent` filtra `user_id`, `week_key`, `kind` e `reversed_at`, mas o índice existente é `(user_id, created_at)`                                                                                                        | Criar índice `(user_id, week_key, kind)` parcial `WHERE reversed_at IS NULL`.                                                  |
| **Cobertura de testes de gamificação**                  | Projeção após estorno/decay, auditoria e reparo já têm testes; ainda faltam testes dos tipos DBAL `player_id`/`xp_event_id`, da segunda atividade da semana (meta semanal) e da idempotência do bônus semanal após estorno            | Adicionar os testes restantes.                                                                                                 |
| **Campos de decay redundantes**                         | `last_decay_period_end` e `decayedPeriods` guardam a mesma informação; só `decayedPeriods` é usado no cálculo                                                                                                                         | Manter apenas `decayedPeriods` ou usar `last_decay_period_end` somente como diagnóstico e documentar a escolha.                |
| **Projeção de nível fora de transação no onboarding**   | `CompleteOnboardingHandler` salva o usuário e publica o evento; a concessão de XP e a projeção de nível ocorrem em flushes separados, sem uma transação única                                                                         | Envolver o handler em `transactional` ou criar uma unidade de trabalho que abranja usuário, XP e projeção.                     |
| **Gamificação sem feature flag**                        | Não há como desligar XP, ofensiva ou deleveling por configuração em um rollout                                                                                                                                                        | Adicionar `GAMIFICATION_ENABLED` e verificar nos listeners/endpoints; útil para rollback e testes A/B.                         |
| **Sem métricas ou observabilidade de gamificação**      | Não há contadores de XP concedido, decays, estornos, erros de listener ou lag do scheduler                                                                                                                                            | Adicionar logs estruturados e métricas (XP/dia, decay/dia, erros) no `Monitoring` ou no provedor de observabilidade.           |
| **Exclusão de conta e retenção do ledger**              | Não há fluxo de exclusão de conta; sem FK, o ledger e o progresso podem ficar órfãos                                                                                                                                                  | Definir a política de retenção/anonimização junto com privacidade (`DEC06`) e implementar cascade ou anonimização.             |
| **Idempotência global em vez de índice parcial**        | `idempotency_key` é único globalmente; reawards de bônus semanal usam sufixo de atividade, e um reaward para a mesma atividade colidiria                                                                                              | Se o caso aparecer, migrar para índice único parcial `WHERE reversed_at IS NULL` e chaves naturais sem sufixo.                 |
| **Saldo clampado no domínio pode esconder divergência** | `UserProgress::award()` faz `max(0, ...)`, então um estorno maior que o saldo zera o saldo em vez de falhar; o ledger pode ficar com soma negativa                                                                                    | A auditoria detecta e reporta; o reparo recusa ledger negativo. Avaliar lançar exceção em estorno que deixaria saldo negativo. |

---

## 6. Checklist de revisão de requisitos

- [x] Definir o que é uma atividade válida.
- [x] Definir estados e janela operacional da atividade.
- [x] Definir margem e exceção de conflito de horário.
- [x] Definir duração opcional e fechamento automático.
- [x] Definir XP por evento e por nível
  ([ADR-032](decisoes.md#adr-032--modelo-de-xp-em-duas-camadas-e-ledger-append-only)
  e [ADR-033](decisoes.md#adr-033--curva-de-níveis-e-xp-por-atividade)).
- [x] Definir fórmula exata de deleveling ([ADR-035](decisoes.md#adr-035--deleveling-e-piso-no-nível-2)).
- [x] Definir ofensiva semanal, bônus de fim de semana e bônus de grupo
  ([ADR-034](decisoes.md#adr-034--ofensiva-semanal-e-bônus-de-constância)).
- [x] Definir que não haverá ofensiva diária na V1.
- [x] Definir permissões de gestão do supervisor na V1 (sem poderes de membros).
- [x] Definir poderes de atividade do supervisor na Fase 5.
- [x] Definir que denúncias e bloqueios ficam adiados na V1
  ([ADR-038](decisoes.md#adr-038--denúncias-e-bloqueios-fora-da-v1)).
- [x] Definir catálogo de notificações, público e canais
  ([ADR-037](decisoes.md#adr-037--notificações-multicanal-com-inbox-canais-e-mercure)).
- [x] Definir leitura, filtro e “marcar todas” (`DEC04`).
- [x] Definir Mercure/SSE como transporte em tempo real do sino
  ([ADR-040](decisoes.md#adr-040--mercure-como-transporte-em-tempo-real-do-sino)).
- [x] Definir suspensão/reativação, sessões, vínculos e histórico
  ([ADR-039](decisoes.md#adr-039--suspensão-global-de-contas)).
- [ ] Definir visibilidade de cada campo de perfil.
- [ ] Definir regras de geração e resgate de vouchers.
- [ ] Definir fórmula do indicador mensal.
- [ ] Validar se todos os critérios de aceitação serão convertidos em testes.
- [x] Fechar a Fase 7 com `just composer quality`.

Cada item fechado deve virar uma entrada em [decisoes.md](decisoes.md) e atualizar o requisito correspondente
em [requisitos/levantamento-de-requisitos.md](requisitos/levantamento-de-requisitos.md).
