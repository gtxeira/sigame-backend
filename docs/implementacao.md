# SIGAME — Implementação e acompanhamento

> **Status:** documento de trabalho
>
> **Última atualização:** 2026-11-05
>
> **Finalidade:** registrar o estado da implementação, o roadmap, as decisões ainda abertas, o histórico de migrations e as dívidas técnicas.

Este documento concentra o que é **acompanhamento de implementação**. Ele não substitui:

- [arquitetura.md](arquitetura.md) — princípios, módulos, camadas e padrões;
- [decisoes.md](decisoes.md) — decisões consolidadas e o porquê de cada uma;
- [requisitos/levantamento-de-requisitos.md](requisitos/levantamento-de-requisitos.md) — requisitos funcionais, regras de negócio e critérios de aceitação.

Quando este documento divergir dos requisitos, a decisão de produto deve ser registrada em `decisoes.md` e o requisito atualizado no levantamento.

---

## 1. Estado atual

### 1.1 Fases

| Fase | Escopo | Estado |
|---|---|---|
| 0 | Infraestrutura base (Docker, PostgreSQL, Doctrine, Shared) | Concluída; integração de storage ainda pendente |
| 1 | Identity: cadastro | Concluída |
| 2 | Identity: autenticação | Concluída |
| 3 | Identity: onboarding | Parcial — domínio pronto, falta camada HTTP |
| 4 | Groups | Não iniciada |
| 5 | Activities | Não iniciada |
| 6 | Gamification | Não iniciada |
| 7 | Notifications + Moderation | Não iniciada |
| 8 | Sponsorship + Reports | Não iniciada |

### 1.2 Capacidades

| Capacidade | Estado | Evidência principal |
|---|---|---|
| Cadastro local com CPF opcional | Implementado | `src/Identity/Application/UseCase/RegisterUser/` |
| Login com e-mail e senha | Implementado | `src/Identity/Application/UseCase/AuthenticateUser/` |
| Login social com Google via authorization code | Implementado | `src/Identity/Application/UseCase/AuthenticateWithSocialProvider/` e `src/Identity/Infrastructure/Social/` |
| Access token JWT + refresh token opaco | Implementado | `src/Identity/Application/Service/TokenPairIssuer.php` |
| Rotação de refresh com detecção de reuso | Implementado | `TokenPairIssuer::rotate()` |
| Logout e logout de todos os dispositivos | Implementado | `src/Identity/Application/UseCase/Logout/` |
| Dados do usuário autenticado | Implementado | `src/Identity/Presentation/Http/Controller/MeController.php` |
| Definição de CPF write-once | Implementado | `AssignCpfHandler` e `PATCH /api/v1/users/me/cpf` |
| Rate limiting de login, social, refresh e CPF | Implementado | `config/packages/rate_limiter.php` |
| Health checks | Implementado | `src/Monitoring/` |
| Onboarding | Parcial | Regras em `User::completeOnboarding()`; falta handler e controller |
| Upload de foto de perfil em S3 | Não iniciado | Pacotes instalados, sem adapter de storage |
| CRUD administrativo de usuários | Não iniciado | Planejado |
| Gamificação | Não iniciada | Evento `UserRegistered` é publicado, mas não há listener |
| Demais módulos de negócio | Não iniciados | Groups, Activities, Notifications, Moderation, Sponsorship, Reports |

### 1.3 O que existe hoje no HTTP

Rotas confirmadas por `bin/console debug:router`:

| Método | Rota | Autenticação |
|---|---|---|
| `POST` | `/api/v1/auth/register` | Pública |
| `POST` | `/api/v1/auth/login` | Pública |
| `POST` | `/api/v1/auth/social/{provider}` | Pública |
| `POST` | `/api/v1/auth/refresh` | Cookie `refresh_token` |
| `POST` | `/api/v1/auth/logout` | Cookie `refresh_token` |
| `POST` | `/api/v1/auth/logout/all` | Bearer JWT |
| `GET` | `/api/v1/users/me` | Bearer JWT |
| `PATCH` | `/api/v1/users/me/cpf` | Bearer JWT |
| `GET` | `/api/health/liveness` | Pública |
| `GET` | `/api/health/readiness` | Pública |

Não existe rota de onboarding nem página inicial. A documentação interativa fica em `/doc` e o JSON OpenAPI em `/doc.json`.

---

## 2. Roadmap

O roadmap é organizado por fases entregáveis. Cada fase só é considerada concluída quando os testes que exercitam o comportamento pelo caminho do chamador estiverem verdes.

### Fase 0 — Infraestrutura base

- [x] Instalar dependências de framework, segurança, serialização, validação, JWT, lock e rate limiting.
- [x] Configurar Docker Compose com FrankenPHP, PostgreSQL 18 e Garage (S3).
- [x] Configurar Doctrine, migrations e tipos customizados.
- [x] Estruturar `src/Shared/` com VOs base (`BaseId`, `Email`, `Cpf`, `Instant`, `Modality`).
- [ ] Implementar adapter de storage S3 via Flysystem e ligar o upload de foto de perfil.

### Fase 1 — Identity: cadastro

- [x] Entidade `User` com domínio rico.
- [x] `RegisterUserCommand` e `RegisterUserHandler`.
- [x] `RegistrationController`.
- [x] Migrations Doctrine.
- [x] Testes de unidade, integração e HTTP do cadastro.

### Fase 2 — Identity: autenticação

- [x] `user_identities` 1:N (local + Google) e CPF opcional/write-once.
- [x] Entidade `RefreshToken` opaca, com família, rotação e detecção de reuso.
- [x] `LcobucciAccessTokenGenerator` e `OpaqueRefreshTokenGenerator`.
- [x] `TokenPairIssuer` com emissão, rotação, revogação e limite de famílias.
- [x] `AuthController` com login, social, refresh, logout e logout/all.
- [x] `MeController` com `/users/me` e `/users/me/cpf`.
- [x] Adapter de segurança (`SecurityUser`, `SecurityUserProvider`, `ActiveUserChecker`).
- [x] Autenticador de access token Bearer.
- [x] Google via authorization code + JWKS.
- [x] Rate limiting de login, social, refresh e CPF.
- [x] Testes de unidade, integração e funcionais dos fluxos principais.
- [ ] Teste funcional do login Google com sucesso (hoje o fluxo bem-sucedido é coberto por teste de unidade com provedor mockado).

### Fase 3 — Identity: onboarding

- [x] Regras de onboarding no agregado `User`.
- [x] Value objects de altura, peso, modalidades e status de onboarding.
- [x] Evento `OnboardingCompleted` (classe definida).
- [ ] `CompleteOnboardingCommand` e `CompleteOnboardingHandler`.
- [ ] `OnboardingController` e DTOs de request/response.
- [ ] Publicação efetiva de `OnboardingCompleted` e integração com Gamification.
- [ ] Teste funcional do fluxo registro → onboarding → nível 2.

### Fase 4 — Groups

- [ ] Entidades `Group` e `Membership`.
- [ ] Casos de uso `CreateGroup`, `RequestMembership`, `ApproveMembership`, `RemoveMember` e `DesignateSupervisor`.
- [ ] Controllers, autorização e testes de limites, estados e permissões.

### Fase 5 — Activities

- [ ] Entidades `Activity` e `Attendance`.
- [ ] Ciclo de vida da atividade (agendada → iniciada → finalizada).
- [ ] Check-in e validação de presença.
- [ ] Conflito de horário.
- [ ] Testes de fluxo, conflito e idempotência.

### Fase 6 — Gamification

- [ ] `UserProgress`, `XpCalculator` e `DelevelingPolicy`.
- [ ] Listener `UserRegistered → cria progresso inicial`.
- [ ] Listener `OnboardingCompleted → concede nível 2`.
- [ ] Listener `AttendanceValidated → AwardXp`.
- [ ] Comando de deleveling (cron/scheduler).
- [ ] Testes de progressão, deleveling e idempotência.

### Fase 7 — Notifications + Moderation

- [ ] Notificações internas de atividades e mudanças de grupo.
- [ ] Denúncias, bloqueio entre usuários e suspensão global.

### Fase 8 — Sponsorship + Reports

- [ ] Patrocínios, benefícios e vouchers.
- [ ] Indicadores mensais de desempenho dos grupos.

---

## 3. Decisões em aberto

As decisões abaixo ainda precisam ser fechadas antes da implementação do módulo correspondente. Quando uma decisão for tomada, mova o registro para [decisoes.md](decisoes.md) e atualize o requisito relacionado.

| ID | Questão | Impacto | Prioridade | Status |
|---|---|---|---|---|
| `DEC01` | Margem de conflito de horários e possibilidade de exceção | Atividades | Alta | Pendente |
| `DEC02` | Tabela de XP por nível e evento | Gamificação | Alta | Pendente |
| `DEC03` | Fórmula exata do deleveling | Gamificação | Alta | Pendente |
| `DEC04` | Comportamento das notificações lidas/arquivadas | Notificações | Média | Pendente |
| `DEC05` | Efeitos do bloqueio entre usuários | Moderação | Alta | Pendente |
| `DEC06` | Matriz final de visibilidade de dados | Privacidade | Alta | Pendente |
| `DEC07` | Público, distribuição e limites de vouchers | Vouchers | Alta | Pendente |
| `DEC08` | Fórmula e periodicidade do indicador mensal | Indicadores | Média | Pendente |
| `DEC10` | Janela operacional de check-in, início e finalização | Atividades | Alta | Pendente |
| `DEC11` | Permissões exatas do supervisor | Autorização | Alta | Pendente |
| `DEC12` | Regra de atividade válida e mínimo de participantes | Atividades/XP | Alta | Pendente |

Decisões já fechadas (`DEC09`, `DEC13` e `DEC14`) estão registradas em [decisoes.md](decisoes.md).

### Decisões técnicas em aberto

| Tema | O que falta definir |
|---|---|
| Rate limiter em produção | Com mais de uma instância, o pool `cache.rate_limiter` precisa apontar para armazenamento compartilhado (Redis ou banco). Hoje o padrão é o cache da aplicação. |
| Limpeza de refresh tokens | Não há rotina para remover tokens expirados ou revogados antigos. |
| Storage de fotos | Definir bucket, política de acesso, limites de tamanho e formato antes de ligar o upload. |
| Mercure | O template sobe um hub Mercure, mas a aplicação ainda não publica nem consome tópicos. |

---

## 4. Migrações do banco de dados

O schema evoluiu por migrations incrementais. Todas estão em `migrations/` e são aplicadas automaticamente pelo entrypoint do container.

| Migration | Data | O que mudou |
|---|---|---|
| `Version20260921225624` | 2026-09-21 | Cria `users` com CPF/e-mail obrigatórios, senha, perfil em JSON, papel, nível, status e onboarding. |
| `Version20261029173517` | 2026-10-29 | Troca `created_at`/`updated_at` para `TIMESTAMP(6) WITH TIME ZONE`, preservando microssegundos e timezone. |
| `Version20261029191722` | 2026-10-29 | Substitui `users.profile` JSON por `height_cm`, `weight_kg` e `photo_url`; cria `user_modalities` e migra os dados existentes. |
| `Version20261104120000` | 2026-11-04 | Cria `user_identities` e `refresh_tokens`; move e-mail/senha para identidades; torna `users.cpf` opcional. |
| `Version20261105153437` | 2026-11-05 | Torna `users.updated_at` obrigatório, preenchendo nulos com `created_at`. |

### Regras

1. Toda alteração de schema passa por `bin/console make:migration` e `doctrine:migrations:migrate`. Nunca use `doctrine:schema:update`.
2. O ambiente de teste usa `zenstruck_foundry.orm.reset.mode: migrate`; o schema vem das migrations, não do mapping.
3. Cada teste de integração/funcional roda dentro de uma transação com rollback (DAMA). Não abra transações explícitas, não use `TRUNCATE` e não chame `commit()`/`rollBack()` dentro do teste.
4. A migration `Version20261104120000` é **parcialmente irreversível**: usuários sem CPF ou sem identidade local impedem restaurar as colunas `NOT NULL` originais. O `down()` deve ser tratado como melhor esforço.
5. Ao adicionar um novo tipo Doctrine, registre-o em `config/packages/doctrine.php` e crie o teste do tipo.

---

## 5. Dívidas técnicas e próximos passos

| Item | Evidência | Ação sugerida |
|---|---|---|
| Onboarding sem HTTP | `User::completeOnboarding()` existe; não há handler nem controller | Criar `CompleteOnboardingCommand/Handler`, `OnboardingController`, DTOs e teste funcional. |
| `OnboardingCompleted` não é publicado | A classe existe, mas nenhum fluxo a dispara | Publicar no handler de onboarding. |
| `UserRegistered` sem consumidor | O evento é publicado no cadastro e no login social | Implementar listener de Gamification quando o módulo existir. |
| Motivo da suspensão não é persistido | `User::suspend(string $reason)` ignora o argumento | Criar histórico de suspensões com autor, motivo e datas. |
| Sem upload de arquivos | Pacotes `league/flysystem` e `league/flysystem-aws-s3-v3` instalados, sem adapter | Implementar `FileStorageInterface` e o adapter S3/Garage. |
| Login Google sem teste funcional de sucesso | `AuthTest` cobre provedor não suportado e payload inválido | Adicionar teste com provedor fake ou cliente HTTP mockado no nível funcional. |
| CI não roda testes | `.github/workflows/ci.yaml` tem os passos de migrations/PHPUnit comentados | Reativar migrations, testes e `doctrine:schema:validate` no CI. |
| Refresh tokens acumulam | Não há comando de limpeza | Criar comando agendado para remover tokens expirados/revogados. |
| CRUD administrativo ausente | Tag `Usuários` planejada em `api-documentation.md` | Implementar quando o módulo de administração existir. |

---

## 6. Checklist de revisão de requisitos

- [ ] Definir o que é uma atividade válida.
- [ ] Definir estados e janela operacional da atividade.
- [ ] Definir XP por evento e por nível.
- [ ] Definir fórmula exata de deleveling.
- [ ] Definir permissões completas do supervisor.
- [ ] Definir efeito do bloqueio entre usuários.
- [ ] Definir visibilidade de cada campo de perfil.
- [ ] Definir regras de geração e resgate de vouchers.
- [ ] Definir fórmula do indicador mensal.
- [ ] Validar se todos os critérios de aceitação serão convertidos em testes.

Cada item fechado deve virar uma entrada em [decisoes.md](decisoes.md) e atualizar o requisito correspondente em [requisitos/levantamento-de-requisitos.md](requisitos/levantamento-de-requisitos.md).
