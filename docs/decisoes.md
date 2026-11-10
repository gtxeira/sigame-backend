# SIGAME — Decisões arquiteturais e de produto

> **Status:** registro vivo
>
> **Última atualização:** 2026-11-10
>
> **Finalidade:** preservar o contexto e o porquê das decisões já tomadas, para orientar mudanças futuras.

Este documento é um registro leve de decisões (ADR — _Architecture Decision Record_). Decisões ainda em aberto ficam em [implementacao.md](implementacao.md). Requisitos funcionais ficam em [requisitos/levantamento-de-requisitos.md](requisitos/levantamento-de-requisitos.md).

## Como registrar uma nova decisão

1. Copie o bloco de uma decisão existente.
2. Dê um identificador sequencial (`ADR-011`, `ADR-012`, ...).
3. Preencha **Contexto**, **Decisão**, **Alternativas** e **Consequências**.
4. Linke os arquivos, requisitos ou testes afetados.
5. Atualize o índice abaixo.

Não altere o texto de uma decisão antiga para "corrigir" a história: se a decisão mudou, registre uma nova decisão e marque a anterior como substituída.

## Índice

| ID | Decisão | Status |
|---|---|---|
| [ADR-001](#adr-001--monolito-modular-com-clean-architecture) | Monolito modular com Clean Architecture | Vigente |
| [ADR-002](#adr-002--comunicação-entre-módulos-por-domain-events) | Comunicação entre módulos por Domain Events | Vigente (emendado pelo ADR-030) |
| [ADR-003](#adr-003--access-jwt--refresh-token-opaco-rotativo) | Access JWT + refresh token opaco rotativo | Vigente |
| [ADR-004](#adr-004--users--user_identities-1n) | `users` + `user_identities` 1:N | Vigente |
| [ADR-005](#adr-005--cpf-opcional-e-write-once) | CPF opcional e write-once | Vigente |
| [ADR-006](#adr-006--catálogo-de-erros-e-status-http-derivado) | Catálogo de erros e status HTTP derivado | Vigente |
| [ADR-007](#adr-007--testes-em-unit-functional-e-integration) | Testes em Unit, Functional e Integration | Vigente |
| [ADR-008](#adr-008--configuração-em-php-com-autowiring) | Configuração em PHP com autowiring | Vigente |
| [ADR-009](#adr-009--postgresql-18-com-doctrine-migrations) | PostgreSQL 18 com Doctrine Migrations | Vigente |
| [ADR-010](#adr-010--arquivos-em-s3-via-flysystemgarage) | Arquivos em S3 via Flysystem/Garage | Vigente |
| [ADR-011](#adr-011--e-mail-canônico-e-vínculo-explícito-de-identidade-social) | E-mail canônico e vínculo explícito de identidade social | Vigente |
| [ADR-012](#adr-012--fluxo-oauth-com-state-e-pkce-no-backend) | Fluxo OAuth com state e PKCE no backend | Vigente |
| [ADR-013](#adr-013--tokens-de-uso-único-para-verificação-de-e-mail-e-reset-de-senha) | Tokens de uso único para verificação de e-mail e reset de senha | Vigente |
| [ADR-014](#adr-014--e-mails-de-identidade-assíncronos-com-payload-cifrado) | E-mails de identidade assíncronos com payload cifrado | Vigente |
| [ADR-015](#adr-015--onboarding-como-ação-única-e-evento-de-domínio) | Onboarding como ação única e evento de domínio | Vigente (emendada pelo ADR-017) |
| [ADR-016](#adr-016--upload-de-foto-de-perfil-com-chave-em-s3) | Upload de foto de perfil com chave em S3 | Vigente |
| [ADR-017](#adr-017--perfil-parcial-e-onboarding-restrito-a-modalidades) | Perfil parcial e onboarding restrito a modalidades | Vigente |
| [ADR-018](#adr-018--foto-de-perfil-quadrada-em-512-no-cliente) | Foto de perfil quadrada em 512 no cliente | Vigente |
| [ADR-019](#adr-019--agregado-de-grupos-com-episódios-de-participação) | Agregado de grupos com episódios de participação | Vigente |
| [ADR-020](#adr-020--consulta-síncrona-de-nível-entre-módulos) | Consulta síncrona de nível entre módulos | Vigente |
| [ADR-021](#adr-021--campos-visibilidade-e-busca-de-grupos) | Campos, visibilidade e busca de grupos | Vigente |
| [ADR-022](#adr-022--supervisor-sem-poderes-de-gestão-na-v1) | Supervisor sem poderes de gestão na V1 | Vigente (emendado pelo ADR-027) |
| [ADR-023](#adr-023--criador-e-administradores-independentes) | Criador e administradores independentes | Vigente |
| [ADR-024](#adr-024--conflito-de-horário-sem-margem-e-sem-exceção) | Conflito de horário sem margem e sem exceção | Vigente |
| [ADR-025](#adr-025--janela-operacional-da-atividade-check-in-e-validação) | Janela operacional da atividade, check-in e validação | Vigente |
| [ADR-026](#adr-026--atividade-válida-para-xp-e-mínimo-de-participantes) | Atividade válida para XP e mínimo de participantes | Vigente |
| [ADR-027](#adr-027--poderes-de-atividade-do-supervisor) | Poderes de atividade do supervisor | Vigente |
| [ADR-028](#adr-028--modelo-de-activities-janela-inscrição-presença-e-histórico) | Modelo de Activities | Vigente |
| [ADR-029](#adr-029--consulta-síncrona-de-grupo-por-activities) | Consulta síncrona de grupo por Activities | Vigente |
| [ADR-030](#adr-030--importação-de-eventos-entre-módulos) | Importação de eventos entre módulos | Vigente |
| [ADR-031](#adr-031--duração-opcional-e-fechamento-automático-de-atividades) | Duração opcional e fechamento automático | Vigente |
| [ADR-032](#adr-032--modelo-de-xp-em-duas-camadas-e-ledger-append-only) | Modelo de XP em duas camadas e ledger append-only | Vigente |
| [ADR-033](#adr-033--curva-de-níveis-e-xp-por-atividade) | Curva de níveis e XP por atividade | Vigente |
| [ADR-034](#adr-034--ofensiva-semanal-e-bônus-de-constância) | Ofensiva semanal e bônus de constância | Vigente |
| [ADR-035](#adr-035--deleveling-e-piso-no-nível-2) | Deleveling e piso no nível 2 | Vigente |
| [ADR-036](#adr-036--nível-como-projeção-de-identity) | Nível como projeção de Identity | Vigente (dívida assumida) |
| [ADR-037](#adr-037--notificações-multicanal-com-inbox-canais-e-mercure) | Notificações multicanal com inbox, canais e Mercure | Vigente |
| [ADR-038](#adr-038--denúncias-e-bloqueios-fora-da-v1) | Denúncias e bloqueios fora da V1 | Vigente |
| [ADR-039](#adr-039--suspensão-global-de-contas) | Suspensão global de contas | Vigente |
| [ADR-040](#adr-040--mercure-como-transporte-em-tempo-real-do-sino) | Mercure como transporte em tempo real do sino | Vigente |
| [ADR-041](#adr-041--catálogo-de-regras-de-gamificação-pela-api) | Catálogo de regras de gamificação pela API | Vigente |

Decisões de produto fechadas: [`DEC09`](#dec09--backend-em-symfony), [`DEC13`](#dec13--cpf-opcional-no-cadastro-e-write-once), [`DEC14`](#dec14--login-social-google-nesta-entrega), [`DEC01`](#adr-024--conflito-de-horário-sem-margem-e-sem-exceção), [`DEC10`](#adr-025--janela-operacional-da-atividade-check-in-e-validação), [`DEC11`](#adr-027--poderes-de-atividade-do-supervisor), [`DEC12`](#adr-026--atividade-válida-para-xp-e-mínimo-de-participantes), [`DEC02`](#adr-033--curva-de-níveis-e-xp-por-atividade), [`DEC03`](#adr-035--deleveling-e-piso-no-nível-2), [`DEC04`](#adr-037--notificações-multicanal-com-inbox-canais-e-mercure) e [`DEC05`](#adr-038--denúncias-e-bloqueios-fora-da-v1).

---

## ADR-001 — Monolito modular com Clean Architecture

### Contexto

O SIGAME tem domínios distintos (identidade, grupos, atividades, gamificação, moderação, patrocínios, notificações e indicadores), mas está na V1 de um projeto acadêmico. Microsserviços adicionariam custo operacional sem necessidade comprovada.

### Decisão

Adotar um **monolito modular**: cada módulo funcional vive em seu próprio namespace em `src/` e organiza suas camadas como `Domain → Application → Infrastructure → Presentation`. O módulo `Shared` funciona como kernel e não pode importar módulos funcionais.

### Alternativas consideradas

- Monolito em camadas globais (`Controller`, `Service`, `Repository`): simples no início, mas mistura domínios e dificulta a evolução.
- Microsserviços: isolamento forte, porém com custo de deploy, observabilidade e consistência desproporcional à V1.

### Consequências

- Fronteiras são verificadas pelo teste `tests/Unit/Architecture/ModuleBoundariesTest.php`.
- Um módulo novo precisa declarar apenas as camadas que fazem sentido.
- A estrutura e o checklist de novo módulo estão em [arquitetura.md](arquitetura.md).

---

## ADR-002 — Comunicação entre módulos por Domain Events

### Contexto

Módulos precisam reagir a fatos de outros módulos sem criar acoplamento direto entre classes concretas. Exemplo: Gamification deve reagir ao cadastro e à conclusão de onboarding.

### Decisão

A comunicação _cross-module_ ocorre exclusivamente por **Domain Events**. Os eventos implementam `DomainEventInterface` e são publicados por uma porta em `Shared/Application/Event/`, adaptada para o dispatcher do Symfony.

### Alternativas consideradas

- Chamadas diretas entre serviços de módulos: mais simples, mas cria dependência concreta e quebra a fronteira.
- Mensageria assíncrona desde o início: desejável no futuro, mas adiciona infraestrutura sem consumidores reais na V1.

### Consequências

- Listeners são registrados com `#[AsEventListener]`.
- O evento `UserRegistered` já é publicado; ainda não há listener de Gamification.
- A integração assíncrona via Messenger pode ser adicionada depois sem mudar o contrato dos eventos.

> **Emenda (ADR-030):** a importação de classes de evento entre módulos funcionais é a única exceção permitida às fronteiras, para viabilizar listeners tipados.

---

## ADR-003 — Access JWT + refresh token opaco rotativo

### Contexto

A API é consumida por uma SPA e precisa de autenticação stateless para o tráfego comum, mas também de sessões revogáveis e resistentes a roubo de credencial.

### Decisão

Usar:

- **access token JWT** HS256, curta duração (15 minutos), stateless e não persistido;
- **refresh token opaco** (`rt_` + 64 caracteres hexadecimais), persistido apenas como SHA-256, entregue em cookie `HttpOnly`;
- **rotação** a cada uso, com família de tokens e detecção de reuso;
- revogação por família (logout) ou de todas as famílias (logout/all).

### Alternativas consideradas

- Sessão em cookie apenas: simples, mas exige estado no servidor e complica o consumo por API.
- Dois JWTs: facilitaria validar o refresh sem banco, mas impediria revogação imediata e detecção de reuso.
- Refresh em `localStorage`: vulnerável a XSS; o cookie `HttpOnly` reduz a superfície.

### Consequências

- O front-end precisa enviar credenciais (`credentials: 'include'` ou `withCredentials`).
- O `SameSite` do cookie é configurável por ambiente.
- A rotação é serializada por lock (`symfony/lock`).
- Detalhes operacionais e exemplos estão em [autenticacao.md](autenticacao.md).

---

## ADR-004 — `users` + `user_identities` 1:N

### Contexto

O usuário pode entrar com e-mail/senha ou com Google. Guardar e-mail e senha diretamente em `users` impediria múltiplos provedores sem duplicar a conta e misturaria perfil, acesso e credenciais.

### Decisão

Separar:

- `users` — perfil, estado de acesso, nível, onboarding e **e-mail canônico único** (`users.email`, `users.email_verified_at`);
- `user_identities` — credenciais de login, com `provider`, `provider_subject`, `email`, `password` e `email_verified_at`;
- a raiz expõe `email()` a partir do e-mail canônico e `password()` a partir da identidade local.

O par `(provider, provider_subject)` é único. O vínculo automático por e-mail só acontece quando o provedor confirma `email_verified=true` **e a conta existente não possui senha local**. Contas com senha local exigem vínculo explícito, conforme o [ADR-011](#adr-011--e-mail-canônico-e-vínculo-explícito-de-identidade-social).

### Alternativas consideradas

- Colunas `email`, `password`, `google_id` em `users`: mais simples, mas não escala para novos provedores e torna o domínio ambíguo.
- Tabela separada apenas para Google: resolveria o caso atual, mas repetiria o problema no próximo provedor.

### Consequências

- A migration `Version20261104120000` moveu os dados existentes para `user_identities`.
- `User::register()` e `User::registerWithProvider()` criam a identidade correspondente.
- A fonte de verdade para autenticação é `user_identities`; o e-mail canônico é `users.email` (ver [ADR-011](#adr-011--e-mail-canônico-e-vínculo-explícito-de-identidade-social)).

---

## ADR-005 — CPF opcional e write-once

### Contexto

Exigir CPF no cadastro aumenta o atrito e exclui parte do público iniciante. Ao mesmo tempo, recompensas externas precisam de identificação real e de proteção contra duplicidade.

### Decisão

O CPF é opcional no cadastro (local e social), único quando informado e **write-once**: depois de definido, o próprio usuário não pode alterá-lo. O CPF é exigido apenas para resgatar recompensas/vouchers externos.

### Alternativas consideradas

- CPF obrigatório desde o cadastro: reduz fraude, mas aumenta abandono e contraria a proposta de inclusão.
- CPF editável: simplifica correções, mas permite troca de identidade e burla de limites.

### Consequências

- Índice único parcial `WHERE cpf IS NOT NULL`.
- O endpoint `PATCH /api/v1/users/me/cpf` aplica a regra e retorna conflito se o CPF já estiver definido.
- Correções administrativas futuras exigirão fluxo próprio.

---

## ADR-006 — Catálogo de erros e status HTTP derivado

### Contexto

Erros de domínio precisam de contrato estável para o cliente, mensagens em PT-BR e status HTTP coerentes, sem espalhar regras de apresentação pelos controllers.

### Decisão

Cada módulo possui um catálogo de códigos (`*ErrorCode`) e fábricas (`*Errors`). Todo `DomainError` carrega um `ErrorKind` (`Authentication`, `Forbidden`, `Validation`, `Conflict`). O `ErrorStatusResolver` deriva o status HTTP com precedência `Authentication > Forbidden > Validation > Conflict`. Erros transversais de HTTP ficam em `ApiErrorCode`.

### Alternativas consideradas

- Lançar exceções com status embutido nos controllers: acopla domínio à camada HTTP.
- Mapear status em cada endpoint: gera divergência e duplicação.

### Consequências

- O valor do código é contrato público; renomear o caso não é _breaking change_, alterar o valor é.
- As mensagens públicas ficam em português e não expõem detalhes internos.
- O teste `tests/Unit/Architecture/ErrorCodeCatalogTest.php` verifica unicidade e classificação.

---

## ADR-007 — Testes em Unit, Functional e Integration

### Contexto

O projeto precisa testar regras puras, a fronteira HTTP e adaptadores reais sem misturar níveis nem depender de banco em testes de domínio.

### Decisão

Dividir a suíte em três grupos:

- `Unit` — entidades, VOs, policies e handlers com ports mockadas (Pest + Mockery);
- `Functional` — fronteira HTTP com `WebTestCase`/`KernelBrowser` e banco real;
- `Integration` — adaptadores reais (repositórios Doctrine, tipos, segurança), sem HTTP.

### Alternativas consideradas

- Uma única suíte com `WebTestCase` para tudo: sobe o kernel sem necessidade e torna os testes de domínio lentos.
- Apenas testes de unidade: não cobre serialização, segurança, rotas e constraints do banco.

### Consequências

- `tests/Pest.php` define os grupos e as bases `TestCase`, `FunctionalTestCase` e `IntegrationTestCase`.
- O banco de teste usa migrations + DAMA + Foundry; cada teste roda em transação com rollback.
- Um comportamento só é considerado entregue quando há teste pelo caminho do chamador.

---

## ADR-008 — Configuração em PHP com autowiring

### Contexto

O projeto migrou as configurações de YAML para PHP e quer evitar definições de serviço manuais.

### Decisão

Usar configuração PHP pura em `config/`, com `autowire` e `autoconfigure` globais para `App\`. Metadados de framework usam atributos PHP. Serviços específicos são resolvidos por type-hint, com `#[Autowire]` ou `#[Target]` quando o type-hint não basta.

### Alternativas consideradas

- YAML/XML: mais familiar para parte do ecossistema, mas sem checagem estática e com mais duplicação.
- Service definitions por módulo: desnecessárias com autowiring.

### Consequências

- Um serviço novo normalmente não exige registro manual.
- Variáveis de ambiente são injetadas com `#[Autowire(env: '...')]`.
- `config/reference.php` é gerado e não deve ser editado à mão.

---

## ADR-009 — PostgreSQL 18 com Doctrine Migrations

### Contexto

O domínio usa UUIDs, timestamps com microssegundos e índices únicos parciais; a V1 precisa de integridade referencial e constraints reais no banco.

### Decisão

Usar **PostgreSQL 18** com Doctrine ORM 3 e Doctrine Migrations. O schema é versionado por migrations; o ambiente de teste aplica migrations antes da suíte e usa rollback por teste.

### Alternativas consideradas

- SQLite: simples para testes, mas não reproduz tipos, constraints e índices parciais do PostgreSQL.
- `doctrine:schema:update`: rápido no desenvolvimento, mas não versiona mudanças e esconde divergências.

### Consequências

- `mapping_types` mapeia `timestamptz` para o tipo `instant`.
- Tipos customizados ficam no módulo dono do VO e são registrados em `config/packages/doctrine.php`.
- O histórico de migrations está em [implementacao.md](implementacao.md#4-migrações-do-banco-de-dados).

---

## ADR-010 — Arquivos em S3 via Flysystem/Garage

### Contexto

Fotos de perfil não devem depender do disco local do container, que é efêmero e não escala horizontalmente.

### Decisão

Usar S3 como contrato de storage, com Flysystem como abstração e Garage como implementação local. Em produção, o mesmo adapter aponta para um provedor S3 compatível.

### Alternativas consideradas

- Disco local: simples, mas inviável com múltiplas instâncias e sem backup adequado.
- Acoplar diretamente ao SDK da AWS: funciona, mas dificulta testes e troca de provedor.

### Consequências

- **Implementado** em `FileStorageInterface`/`FlysystemFileStorage`; a configuração fica em `config/packages/storage.php`.
- Em teste, o adapter é local (`var/storage-test`), sem depender do Garage.
- O bucket local é `profile-images`, escrito pela credencial da aplicação e lido pelo endpoint website do Garage.
- Limites e formatos aceitos estão no [ADR-016](#adr-016--upload-de-foto-de-perfil-com-chave-em-s3).

---

## ADR-011 — E-mail canônico e vínculo explícito de identidade social

### Contexto

O vínculo automático por e-mail permitia um ataque de pré-cadastro: um atacante registrava o e-mail da vítima com uma senha própria e, quando a vítima entrava com Google, a identidade dela era vinculada à conta do atacante — que mantinha a senha e o acesso.

### Decisão

- `users.email` é o e-mail canônico, único e `NOT NULL`; `users.email_verified_at` registra a verificação.
- O vínculo automático só ocorre quando a conta existente **não possui senha local**.
- Contas com senha local recebem `409 SOCIAL_ACCOUNT_LINK_REQUIRED` + `meta.link.token` e precisam autenticar antes de vincular.
- O ticket é de uso único, expira em 10 minutos e é vinculado ao e-mail que originou o conflito.
- A tela de configurações pode iniciar um fluxo `purpose=link` com Bearer e concluir em `/users/me/identities/{provider}`.

### Alternativas consideradas

- Nunca vincular automaticamente: conflita com a RN01.11 e cria duplicatas para contas sociais.
- Vincular sempre e remover a senha local: deixaria a vítima sem forma de recuperar a conta.

### Consequências

- A migration de `users.email` aborta se houver duplicatas, exigindo correção manual.
- O cadastro local passa a enviar verificação de e-mail.
- `GET /api/v1/users/me` expõe `emailVerified`.
- A falha de unicidade de e-mail no banco é traduzida para `EMAIL_ALREADY_EXISTS`, sem 500.

---

## ADR-012 — Fluxo OAuth com state e PKCE no backend

### Contexto

O endpoint aceitava `code`, `redirectUri` e um `codeVerifier` opcional do cliente. Sem um binding do navegador, um código obtido pelo atacante podia ser injetado no navegador da vítima (login CSRF), e o `redirectUri` controlado pelo cliente aumentava a superfície.

### Decisão

- `POST /auth/social/{provider}/start` gera `state` e PKCE (S256), guarda o fluxo no servidor e seta o cookie `social_flow` (`HttpOnly`).
- `POST /auth/social/{provider}/callback` valida cookie + `state`, consome o fluxo e troca o code usando o `redirect_uri` e o `code_verifier` do servidor.
- O redirect URI é escolhido no servidor por `client` (`web`, `dev`); o cliente não envia `redirectUri`, `codeVerifier` nem `name`.
- `kid` desconhecido no JWKS renova as chaves, no máximo uma vez por minuto.

### Alternativas consideradas

- Exigir apenas PKCE do cliente: protege a interceptação do code, mas não o login CSRF, porque o `state` continuaria no front.
- Manter a allowlist de `redirectUri` no request: funcional, porém mantém um campo controlado pelo cliente e permite callbacks públicos de terceiros.

### Consequências

- O contrato anterior de login social foi removido.
- O cookie `social_flow` usa o path `/api/v1` e a mesma configuração de `Secure`/`SameSite` do refresh.
- O fluxo é guardado no pool `cache.social_flow`; em múltiplas instâncias, o pool precisa ser compartilhado.
- A página `/dev/google-oauth` existe apenas em `APP_ENV=dev` e substitui o Playground no fluxo da aplicação.

---

## ADR-013 — Tokens de uso único para verificação de e-mail e reset de senha

### Contexto

O cadastro local não confirmava o e-mail e não havia recuperação de senha. Sem verificação, o endereço não podia ser usado como fator de recuperação nem distinguir o dono real da conta.

### Decisão

- Persistir tokens de uso único em `identity_tokens` apenas como SHA-256, com `purpose`, `expires_at` e `consumed_at`.
- Verificação de e-mail: 24 horas, enviada no cadastro e reenviável autenticado.
- Reset de senha: 30 minutos, solicitação sempre responde `204` (sem enumeração).
- Pedidos repetidos para a mesma conta respeitam um cooldown (`PASSWORD_RESET_COOLDOWN`, 60s); falha no envio consome o token recém-criado para não deixar credencial válida sem entrega.
- O consumo é atômico (`UPDATE ... WHERE consumed_at IS NULL`); reset e troca revogam todas as famílias de refresh tokens.
- O envio fica atrás de `IdentityMailerInterface`, com adapter Symfony Mailer e Mailpit em desenvolvimento.

### Alternativas consideradas

- Token assinado stateless: simples, mas sem revogação, uso único nem auditoria.
- Envio síncrono dentro do handler de cadastro: acoplaria o cadastro ao provedor de e-mail; o listener atual captura falhas e permite reenvio.

### Consequências

- Novas rotas: `auth/email/verify`, `users/me/email/resend`, `auth/password/forgot`, `auth/password/reset` e `users/me/password`.

> **Emenda (2026-11-06):** a rota de reenvio passou de `POST /users/me/email/resend` para `POST /auth/email/verification/resend`, mantendo a exigência de Bearer. O par de verificação ficou sob `/auth/email`.

> **Emenda (2026-11-07):** o reenvio passou a responder `409 EMAIL_ALREADY_VERIFIED` quando o e-mail já está verificado, em vez de `204`. Como a rota é autenticada, informar o estado não vaza a existência de contas; a checagem ocorre antes do rate limit e o cliente deixa de depender de `GET /users/me` para saber se ainda deve esperar a confirmação.
- Contas só sociais podem ganhar uma credencial local via reset (o e-mail já foi verificado pelo provedor).
- O envio é síncrono; a migração para Messenger fica registrada como evolução.

> **Emenda (2026-11-06):** o envio passou a ser assíncrono, conforme o [ADR-014](#adr-014--e-mails-de-identidade-assíncronos-com-payload-cifrado). O token bruto continua fora do banco e é cifrado no payload da fila.

---

## ADR-014 — E-mails de identidade assíncronos com payload cifrado

### Contexto

O envio de e-mail dentro do request de cadastro, reenvio e reset prendia a resposta ao SMTP e não permitia retry. Ao mesmo tempo, o [ADR-013](#adr-013--tokens-de-uso-único-para-verificação-de-e-mail-e-reset-de-senha) determina que apenas o hash do token de uso único é persistido; colocar o token bruto em `messenger_messages` sem proteção reduziria essa garantia.

### Decisão

- Usar **Symfony Messenger** com transporte **Doctrine** (`messenger_messages`, `auto_setup=0`), worker dedicado e `failure_transport` para falhas definitivas.
- Manter a criação do token no request, preservando cooldown, invalidação e a semântica de "um token válido por vez" do reset.
- Publicar mensagens explícitas por caso de uso (`SendEmailVerificationMessage` e `SendPasswordResetMessage`) pela porta `IdentityMailDispatcherInterface`.
- **Cifrar o token bruto** com libsodium `secretbox` (`EncryptedPayload` + `PayloadEncryptor`) antes de enfileirar. O `tokenHash` fica em claro para permitir a compensação.
- O worker decifra, reconstrói `Email`/`Instant` e envia pelo `IdentityMailerInterface`. Retries reutilizam o mesmo token, então e-mails duplicados têm o mesmo link.
- Em falha definitiva, o listener consome o token de reset (como já acontecia no envio síncrono). O token de verificação permanece válido para reenvio.
- Desativar `framework.mailer.message_bus` para que o envio aconteça dentro do handler do worker, sem uma segunda camada de mensageria.

### Alternativas consideradas

- **Manter síncrono:** simples, mas mantém a latência e a perda silenciosa em falhas transitórias de SMTP.
- **Gerar o token no worker:** elimina o segredo da fila, mas exige mover a checagem de cooldown para o worker, serializar por usuário e lidar com retry rotacionando tokens. O comportamento de cooldown deixaria de ser idêntico sob backlog.
- **Usar o `SendEmailMessage` nativo do Mailer:** menos código, mas não permite carregar o `tokenHash` para compensar o token em falha definitiva.
- **Enviar o token em claro na fila:** mais simples, porém contraria o espírito do ADR-013.

### Consequências

- Novos processos: o serviço `worker` no Compose e a receita `just worker`/`worker-logs`.
- A tabela `messenger_messages` é criada por migration própria; o transporte não usa `auto_setup`.
- Nova variável `MESSENGER_MAIL_ENCRYPTION_KEY` (base64 de 32 bytes), obrigatória no web e no worker. Rotação exige drenar a fila antes.
- Entrega é **at-least-once**: um retry pode reenviar o mesmo e-mail; como o token é o mesmo, o link continua válido.
- Mensagens de reset no `failed` são terminais porque o token já foi consumido; use `messenger:failed:remove`, não retry. Verificação pode ser retentada.
- A V1 roda **uma réplica** do worker para preservar a ordem FIFO entre reenvios do mesmo usuário.
- Testes usam `MESSENGER_TRANSPORT_DSN=sync://`, mantendo handlers e `RecordingIdentityMailer` no mesmo processo.

---

## ADR-015 — Onboarding como ação única e evento de domínio

### Contexto

O agregado `User` já concentrava as regras de onboarding, mas faltava expô-las por HTTP e publicar `OnboardingCompleted` para a futura Gamification. Era preciso decidir a forma da rota, o comportamento em repetição, onde ficam as validações e quando o evento é disparado.

### Decisão

- Expor a conclusão como **ação de domínio** em `POST /api/v1/users/me/onboarding`, autenticada por Bearer JWT, na tag `Onboarding`.
- Tratar a conclusão como **única**: depois de concluída, novas tentativas retornam `409 ONBOARDING_ALREADY_COMPLETED` sem conceder novamente o nível 2. O requisito `CA03.2` admite tanto a conclusão única quanto a idempotente; a opção única evita comparação de payload e mantém a regra no agregado.
- Validar **tipo e formato no request** (URL da foto, valores de modalidade) e **regras de negócio no agregado** (faixa de altura e peso, ao menos uma modalidade, modalidade principal entre as selecionadas). Assim o cliente recebe os códigos `USER_HEIGHT_INVALID`, `USER_WEIGHT_INVALID`, `ONBOARDING_NO_MODALITY_SELECTED` e `ONBOARDING_INVALID_PRIMARY_MODALITY`, e `VALIDATION_ERROR` fica reservado a entrada malformada.
- Publicar `OnboardingCompleted` **depois** da persistência, no handler, seguindo o padrão de `UserRegistered`.
- Reutilizar `CurrentUserResponse` e acrescentar altura, peso, foto, modalidades e modalidade principal. A mudança é aditiva e permite que `GET /users/me` mostre o perfil completo.
- Aceitar `photoUrl` como URL livre por enquanto; o upload real depende do adapter S3/Flysystem do [ADR-010](#adr-010--arquivos-em-s3-via-flysystemgarage).

### Alternativas consideradas

- **`PUT /users/me/onboarding`:** sugeriria substituição idempotente, mas o agregado recusa a segunda conclusão.
- **Idempotência por comparação de payload:** devolveria 200 quando os dados fossem iguais, porém exige duplicar a comparação de modalidades e dificulta distinguir "já concluído" de "já concluído com outros dados".
- **Validar faixas no request:** devolveria `VALIDATION_ERROR` genérico e esconderia os códigos de negócio já catalogados.

### Consequências

- O nível 2 é promovido pelo próprio agregado na conclusão; o evento apenas comunica o fato para os listeners da Fase 6.
- A troca de modalidade principal depois de concluído (`RN03.5`) fica como dívida, com caso de uso e endpoint próprios.
- O endpoint é limitado por usuário pelo limiter `user_onboarding`.
- Testes: `CompleteOnboardingHandlerTest` (unidade) e `OnboardingTest` (HTTP), além do caminho de 401 em `AuthHardeningTest`.

> **Emenda (ADR-017):** altura, peso e foto saíram do onboarding. A rota passou a aceitar apenas `modalities` e `primaryModality`; os demais dados são atualizados por `PATCH /users/me/profile` e pelo ciclo próprio da foto.

---

## ADR-016 — Upload de foto de perfil com chave em S3

### Contexto

O onboarding aceitava `photoUrl` como string livre, o que permitia apontar para qualquer origem e não garantia que o arquivo existisse. Era preciso definir o transporte do arquivo, onde a referência é persistida e como o cliente lê a imagem.

### Decisão

- Expor `POST /api/v1/users/me/photo` com `multipart/form-data` e campo `photo`. PHP não popula `$_FILES` em `PUT` multipart, por isso o upload usa `POST` também para substituição.
- Validar com `#[MapUploadedFile]` e `Image`: até 2 MB, JPEG/PNG/WebP e **quadrado em 512×512** (tolerância de 5 px por lado), conforme o [ADR-018](#adr-018--foto-de-perfil-quadrada-em-512-no-cliente). O limite do PHP sobe para `upload_max_filesize=5M`/`post_max_size=10M` para o erro chegar como `422`.
- Persistir apenas a **chave do objeto** em `users.photo_key`, no formato `users/{userId}/{uuid}.{ext}`; a URL é derivada de `S3_PUBLIC_URL` na resposta.
- Devolver `CurrentUserResponse` no upload e `204` no `DELETE /api/v1/users/me/photo`, que é idempotente.
- Serializar uploads por usuário com `LockFactory`; se o save falhar, apagar o objeto recém-gravado. O objeto anterior é apagado em best-effort depois do sucesso.
- No Garage local, habilitar leitura anônima pelo website (`just storage-setup`) e manter a escrita privada pela credencial da aplicação.

### Alternativas consideradas

- **URL pré-assinada:** reduz o tráfego no backend, mas exige CORS, validação posterior do objeto e mais estados no cliente.
- **Base64 no JSON:** reaproveita o contrato atual, porém infla o payload e ignora o content-type de upload.
- **Manter `photoUrl`:** não garante existência, formato ou origem do arquivo.
- **`detectCorrupted`:** a constraint exige GD; como a extensão não está instalada, derrubaria a requisição com `500`.

### Consequências

- A URL pública não é persistida: trocar bucket, domínio ou estratégia de leitura não exige migration.
- A chave aleatória evita adivinhação, mas o bucket é público na V1. A matriz definitiva de visibilidade (`DEC06`) pode exigir proxy ou URL assinada depois; a chave já prepara essa migração.
- Não há resize, thumbnail, leitura de EXIF nem validação de imagem corrompida no servidor; o arquivo original é armazenado.
- A migration `Version20261108120000` renomeia a coluna e limpa URLs antigas, que não são chaves válidas.
- Testes: `FlysystemFileStorageTest`, `S3ProfilePhotoStorageTest`, `UploadProfilePhotoHandlerTest`, `RemoveProfilePhotoHandlerTest` e `MePhotoTest`.

> **Emenda (ADR-018):** o limite aceito deixou de ser 200×200 a 4096×4096 e passou a ser **quadrado, 512×512 com tolerância de 5 px por lado (507 a 517)**. O arquivo continua sendo armazenado sem re-encode; a conversão para WebP, o crop no servidor, a remoção de EXIF, a compressão e as derivadas estão registradas como dívida técnica em [implementacao.md §5.1](implementacao.md#51-processamento-de-imagem).

---

## ADR-017 — Perfil parcial e onboarding restrito a modalidades

### Contexto

O onboarding misturava progressão (modalidades e modalidade principal) com dados de perfil (altura, peso e foto). Como altura e peso são opcionais e a foto não é obrigatória, uma falha de storage podia impedir a promoção ao nível 2. Também não existia forma de editar o perfil depois da conclusão.

### Decisão

- `POST /api/v1/users/me/onboarding` aceita apenas `modalities` e `primaryModality`; `User::completeOnboarding()` deixou de receber altura, peso e foto.
- Criar `PATCH /api/v1/users/me/profile` com semântica de **JSON Merge Patch (RFC 7396)**:
  - campo ausente não altera;
  - `null` limpa somente `heightCm` e `weightKg`;
  - `name`, quando enviado, é trimado e não pode ser vazio, com mínimo de 2 e máximo de 255 caracteres;
  - corpo sem nenhum campo conhecido (`{}`) retorna `422 VALIDATION_ERROR` para não gerar operação sem efeito.
- A presença é conferida no controller com `Request::getPayload()->has()`, porque a desserialização não distingue campo ausente de campo `null`.
- Altura e peso continuam validados no domínio e retornam `USER_HEIGHT_INVALID`/`USER_WEIGHT_INVALID` sem persistir nenhuma alteração quando um dos campos é inválido.
- O media type aceito é `application/json`; `application/merge-patch+json` fica como evolução.

### Alternativas consideradas

- **`PUT /users/me/profile`:** expressa substituição completa, mas obriga o cliente a reenviar todos os campos para alterar um só.
- **PATCH sem regra de corpo vazio:** permite `200` sem efeito, o que dificulta detectar clientes errados.
- **Aceitar `name: null`:** apagaria o nome, que é obrigatório no agregado.

### Consequências

- O nível 2 depende apenas das modalidades; falha de storage ou dados físicos não bloqueiam a progressão.
- `Profile` ganhou withers (`withName`, `withHeight`, `withWeight`, `withPhoto`) e `User` ganhou `changeProfile()`/`changePhoto()`.
- A foto tem ciclo de vida próprio e nunca é alterada pelo PATCH de perfil.
- Testes: `UpdateProfileHandlerTest` e `MeProfileTest` cobrem campo ausente, `null`, valor, corpo vazio e tipos inválidos.
- A aceitação de `application/merge-patch+json` exige registrar o formato no `Request` e fica como dívida.

---

## ADR-018 — Foto de perfil quadrada em 512 no cliente

### Contexto

O upload aceitava qualquer imagem entre 200×200 e 4096×4096. Isso deixava a qualidade e o enquadramento a cargo de cada cliente, permitia originais grandes com metadados no bucket público e obrigava a interface a lidar com proporções diferentes em cada avatar. Como a UI de perfil terá um passo de enquadramento, o cliente consegue entregar uma imagem já normalizada.

### Decisão

- Exigir **quadrado, 512×512, com tolerância de 5 px por lado** (507 a 517), mantendo a proporção 1:1 exata.
- Manter o teto de **2 MB** e os formatos JPEG, PNG e WebP.
- O **enquadramento é escolhido na UI do cliente**; a API não recorta, redimensiona nem reescreve o arquivo.
- Expressar a regra com `minWidth`/`minHeight: 507`, `maxWidth`/`maxHeight: 517` e `allowLandscape: false`/`allowPortrait: false`. Não usar `minRatio`/`maxRatio`: eles toleram diferença de proporção, e o validador arredonda a razão para 2 casas, o que produz uma janela confusa em 512 px.
- Registrar a conversão para WebP, o crop no servidor, a remoção de EXIF/metadados, a compressão e a geração de múltiplos tamanhos como **dívida técnica** em [implementacao.md §5.1](implementacao.md#51-processamento-de-imagem).

### Alternativas consideradas

- **Aceitar originais e recortar/compactar no endpoint:** melhora a UX de quem envia de qualquer origem, mas exige GD/Imagick, consome memória (12 MP chega perto do `memory_limit` de 128M) e cria um caminho de erro novo para imagem corrompida.
- **Pipeline assíncrono pelo worker:** só compensa para originais grandes ou geração de derivadas em volume; a 512 px o custo síncrono é de poucos milissegundos.
- **Serviço de redimensionamento on-the-fly (imgproxy/Thumbor):** resolve derivadas sem código, mas adiciona infraestrutura e um ponto de falha na leitura.

### Consequências

- A API fica simples: valida cabeçalho de imagem e tamanho, sem decode e sem dependência de GD.
- O contrato é explícito e testável; um cliente que envie 4:3 ou 1024×1024 recebe `422` com mensagem em PT-BR.
- Um cliente fora do contrato ainda pode publicar EXIF no bucket público — risco residual aceito até a dívida do §5.1 ser paga.
- O egress por avatar cai para ~100–300 KB, mas listas ainda servem o arquivo de 512 px; thumbnails continuam na dívida.
- Testes: `MePhotoTest` cobre 507/512/517 aceitos, 506/518 rejeitados e 517×507 / 507×517 rejeitados por proporção.

---

## ADR-024 — Conflito de horário sem margem e sem exceção

### Contexto

O RF11 define conflito como sobreposição de intervalos, mas o `DEC01` deixava em aberto margem de deslocamento e override administrativo.

### Decisão

- Conflito por sobreposição estrita: `existente.inicio < nova.fim && existente.fim > novo.inicio`.
- Margem de **0 minutos**; atividades encostadas não conflitam.
- **Sem override administrativo** na V1.
- Verificação na inscrição e na remarcação, para todos os inscritos ativos.
- Atividade iniciada usa `auto_close_at` como fim efetivo.
- Transação + lock da atividade + advisory lock por usuário.

### Alternativas consideradas

- Margem de 15/30 min: útil para deslocamento, mas não prevista no RF11.
- Override administrativo: contraria `CA11.4` e abre brecha para inscrições conflitantes.

### Consequências

- Atividades adjacentes são aceitas.
- Uma remarcação pode ser recusada por conflito de um inscrito; o admin deve resolver a inscrição antes.
- `ActivityPolicy::CONFLICT_MARGIN_MINUTES = 0` permite evolução futura.

---

## ADR-025 — Janela operacional da atividade, check-in e validação

### Contexto

O ciclo de vida tinha estados, mas não definia quando cada transição era permitida.

### Decisão

| Operação | Regra |
|---|---|
| Criar/remarcar | `SCHEDULED`; novo início no futuro |
| Inscrever-se/cancelar inscrição | `SCHEDULED`, antes de `startsAt` |
| Check-in | `SCHEDULED`/`STARTED`, de `startsAt - 30 min` até `endsAt + 30 min` |
| Iniciar | `SCHEDULED`, de `startsAt - 15 min` até `startsAt + 2 h` |
| Finalizar | `STARTED`, até `startedAt + 24 h` |
| Validar presença | `FINISHED`, de `max(finishedAt, endsAt + 30 min)` até `finishedAt + 48 h` |
| Cancelar | `SCHEDULED` |
| Invalidar | `STARTED`/`FINISHED`, até 48 h após o encerramento; admin |

Operações terminais são idempotentes no mesmo estado. As janelas ficam em `ActivityPolicy` e os métodos de domínio recebem `Instant $now` explícito.

### Alternativas consideradas

- Sem janelas: permitiria iniciar/finalizar a qualquer momento e fragilizaria XP.
- Janela única de 24 h: simples, mas não protege o check-in nem a validação.

### Consequências

- Não é necessário scheduler para impor as janelas; elas são verificadas em cada operação.
- Atividade não iniciada pode ser cancelada manualmente; o scheduler cobre o caso após 3 h.
- Testes de domínio usam `Instant` fixo, sem depender do relógio real.

---

## ADR-026 — Atividade válida para XP e mínimo de participantes

### Contexto

O `DEC12` não definia o que é uma atividade válida nem o mínimo de participantes para XP.

### Decisão

- Atividade elegível: `FINISHED`, não cancelada/invalidada e com **pelo menos 2 check-ins**.
- A validação abre depois do fechamento do check-in, tornando a contagem estável.
- Cada presença validada gera XP uma única vez.
- `AttendanceValidated` carrega `xpEligible`.
- A validação pode ocorrer com menos de 2 check-ins, mas com `xpEligible=false`.
- `ActivityInvalidated` carrega os usuários validados para reversão pela Gamification.

### Alternativas consideradas

- Mínimo de 1 check-in: mais simples, mas permite XP solo e enfraquece o caráter de grupo.
- Mínimo de 2 validações: criaria problema de ordem, pois a primeira validação não saberia que a segunda ocorreria.

### Consequências

- A Gamification deve ignorar `xpEligible=false`.
- Invalidação posterior exige compensação de XP.
- `ActivityPolicy::MIN_CHECKED_IN_FOR_XP = 2`.

---

## ADR-027 — Poderes de atividade do supervisor

### Contexto

O ADR-022 deixou os poderes de atividade do supervisor para a Fase 5.

### Decisão

O supervisor é um **operador da atividade**, não um gestor:

- pode fazer check-in próprio, validar e listar presenças, iniciar e finalizar;
- não pode criar, editar, remarcar, cancelar, invalidar nem alterar informações sensíveis;
- continua sem poderes de gestão de membros;
- perde os poderes imediatamente ao ser removido ou rebaixado.

Admin do grupo e admin SIGAME podem executar todas as operações. O criador só tem poderes enquanto for admin, conforme o ADR-023.

### Alternativas consideradas

- Supervisor com gestão completa: amplia o risco e contraria a decisão de que informações sensíveis são do admin.
- Supervisor apenas com validação: impediria que ele conduzisse a atividade no dia.

### Consequências

- A matriz de permissões do levantamento foi atualizada.
- Testes HTTP cobrem `403` do supervisor em criação e validação prematura.

---

## ADR-028 — Modelo de Activities: janela, inscrição, presença e histórico

### Contexto

Os requisitos listavam apenas campos mínimos e não detalhavam inscrição, visibilidade, histórico ou invalidação.

### Decisão

- Campos: `title` obrigatório, `description` opcional, `startsAt`, `estimatedDurationMinutes` opcional, `meetingPoint`, `route` e `estimatedDistanceMeters`.
- `ActivityWindow` guarda `startsAt`/`endsAt`; sem duração, a janela de planejamento usa 120 min.
- `Attendance` é um episódio append-only; o índice único parcial permite um episódio ativo por par atividade/usuário.
- A inscrição é self-service, apenas para membro aprovado; o criador não é inscrito automaticamente.
- Atividades são visíveis apenas para membros aprovados; admin SIGAME pode consultar.
- Remarcações geram `activity_reschedules` append-only.
- Invalidação é exclusiva de admin, com motivo obrigatório.
- Sem recorrência, GPS obrigatório, capacidade por atividade ou status `ABSENT` na V1.
- Grupo que cai para `FORMING` mantém atividades existentes; apenas novas criações são bloqueadas.

### Alternativas consideradas

- Uma linha de presença sobrescrita: mais simples, mas perde o histórico de cancelamento.
- Inscrição automática do criador: criaria conflito e participação involuntária.
- Atividades públicas: exporia ponto de encontro e trajeto.

### Consequências

- Novas tabelas `activities`, `activity_attendances` e `activity_reschedules`.
- `ActivityAccessPolicy` centraliza a autorização sobre o grupo.
- Testes cobrem conflito, idempotência, histórico e visibilidade.

---

## ADR-029 — Consulta síncrona de grupo por Activities

### Contexto

Activities precisa validar o estado do grupo e o papel do usuário na mesma requisição, mas não pode importar classes de Groups.

### Decisão

Declarar em `Shared`:

- `GroupAccessReaderInterface::snapshot(string $groupId, string $userId): ?GroupAccessSnapshot`;
- `GroupAccessReaderInterface::approvedGroupIds(string $userId): list<string>`.

`GroupAccessSnapshot` expõe `acceptsActivities`, `isApprovedMember`, `isAdmin` e `isSupervisor`. O adapter `DoctrineGroupAccessReader` vive em Groups.

### Alternativas consideradas

- Read model atualizado por evento: consistência eventual inaceitável para autorização.
- Consulta direta ao schema de Groups: acopla módulos e ignora o agregado.
- Papéis no JWT: ficam defasados após remoção/rebaixamento.

### Consequências

- É uma extensão documentada do ADR-020.
- A autorização é sempre atual e reflete remoção/rebaixamento imediatamente.

---

## ADR-030 — Importação de eventos entre módulos

### Contexto

O ADR-002 determina comunicação por Domain Events, mas o `ModuleBoundariesTest` proíbe qualquer import entre módulos funcionais. Sem uma exceção para eventos, Gamification e Notifications não conseguem registrar listeners tipados.

### Decisão

Permitir que um módulo funcional importe **somente** classes de evento de outro módulo, no caminho `App\<OutroMódulo>\Domain\...\Event\...`. Entidades, repositórios, serviços, DTOs e controllers continuam proibidos. `Shared` continua sem importar módulos funcionais.

### Alternativas consideradas

- Mover contratos de eventos para `Shared`: isola, mas esvazia o domínio dos módulos e cria um kernel instável.
- Barramento por nome de evento: flexível, porém sem tipagem e mais difícil de testar.

### Consequências

- O `ModuleBoundariesTest` foi ajustado para permitir somente imports de classes de evento entre módulos funcionais.
- A Fase 5 apenas publica eventos; não há listener novo.

---

## ADR-031 — Duração opcional e fechamento automático de atividades

### Contexto

Atividades podiam ficar abertas indefinidamente e a duração era obrigatória, o que não refletia a realidade de caminhadas, corridas e pedais com durações variadas.

### Decisão

- `estimatedDurationMinutes` é opcional (15–1440).
- Sem duração, a janela de planejamento usa 120 min.
- Atividade iniciada com duração fecha em `duração + 50%`, com tolerância mínima de 15 e máxima de 240 min.
- Atividade iniciada sem duração fecha após 12 h.
- Atividade não iniciada é cancelada após 3 h do início previsto.
- O scheduler roda a cada 5 min e executa `CloseExpiredActivitiesMessage` de forma idempotente.
- Fechamento automático grava flags `finishedAutomatically`/`cancelledAutomatically` e publica eventos com `automatic=true`.
- O fechamento automático não cria XP por si só; a regra de 2 check-ins continua valendo.

### Alternativas consideradas

- Fechamento fixo de 12 h para todos: simples, mas ignora a duração informada.
- Cron externo: funcional, porém menos integrado ao worker e ao ambiente Symfony.

### Consequências

- Novas colunas `auto_close_at`, `finished_automatically` e `cancelled_automatically`.
- Novo serviço `scheduler` no Compose e receitas `just scheduler`/`just scheduler-logs`.
- Se o scheduler estiver parado, o fechamento atrasa até a próxima execução.

---

## ADR-032 — Modelo de XP em duas camadas e ledger append-only

### Contexto

A gamificação precisa permitir deleveling, estorno por invalidação, histórico auditável e recálculo futuro de regras sem
apagar o que o usuário já conquistou. Um único campo `xp` não distingue o XP histórico do XP atual e torna estornos e
penalidades ambíguos.

### Decisão

- `xp_lifetime` registra tudo que o usuário conquistou e só diminui quando uma atividade que gerou XP é invalidada.
- `xp_balance` é o XP atual, usado para nível e barra, e pode diminuir por deleveling ou estorno.
- Cada concessão, estorno e penalidade gera um lançamento append-only em `xp_events`.
- Cada lançamento possui `kind`, `amount`, `source_type`, `source_id`, `activity_id`, `activity_date`, `week_key`,
  `rule_version` e `idempotency_key`.
- O `idempotency_key` é único apenas entre lançamentos ativos (`reversed_at IS NULL`). Um estorno marca o original
  como revertido e cria um lançamento negativo, permitindo que a mesma concessão lógica seja refeita depois.
- O saldo e o nível são projeções recalculáveis a partir do ledger, mas ficam materializados em `user_progress` para
  leitura rápida.

### Alternativas consideradas

- Um único campo `xp` mutável: simples, mas perde histórico e dificulta auditoria e recálculo.
- Event sourcing completo com replay em toda leitura: poderoso, porém caro e desnecessário para o volume da V1.
- Não permitir reaward após estorno: evitaria duplicidade, mas impediria corrigir uma semana válida após uma
  invalidação indevida.

### Consequências

- Novas tabelas `user_progress` e `xp_events` e mais escrita por validação.
- O histórico fica auditável e o recálculo de regras é viável.
- A consistência entre ledger, saldo e nível precisa de testes de idempotência e de estorno.
- O requisito detalhado está em `RF12` e `RF13`.

---

## ADR-033 — Curva de níveis e XP por atividade

### Contexto

O `DEC02` deixava em aberto a tabela de XP por nível e os valores por evento. Era preciso uma curva simples de explicar,
que não tornasse o nível 10 inalcançável e que diferenciasse caminhada, corrida e ciclismo sem punir iniciantes.

### Decisão

- A curva é triangular: `T(n) = 50 × n × (n − 1)`, com níveis de 1 a 10.
- O onboarding concede 100 XP uma única vez e coloca o usuário no nível 2.
- O XP de atividade soma base da modalidade, faixa de distância, faixa de duração e bônus de grupo.
- O fim de semana aplica ×1,2 sobre o XP de atividade.
- O fator diário reduz a 4ª atividade do dia a 25% e a 5ª em diante a 0.
- O teto é 100 XP por atividade.
- Não há bônus por dias consecutivos nem bônus de primeira atividade do dia.

As tabelas completas, a ordem de cálculo e os exemplos estão em
[requisitos/levantamento-de-requisitos.md](requisitos/levantamento-de-requisitos.md#rf12--progressão-de-xp-e-níveis).

### Alternativas consideradas

- XP fixo por atividade: simples, mas trata de forma igual uma caminhada curta e um pedal longo.
- Curva linear: previsível, mas torna os níveis altos rápidos demais.
- XP baseado apenas em duração: favoreceria atividades longas e ignoraria a distância, que é obrigatória na V1.

### Consequências

- As regras ficam versionadas em `GamificationRules` (`2026-11-v1`) e cobertas por testes de tabela.
- Mudanças futuras usam `rule_version` e `LEGACY_BONUS` para não reduzir XP antigo.
- O bônus de grupo usa check-ins para que todos os participantes recebam o mesmo valor.

---

## ADR-034 — Ofensiva semanal e bônus de constância

### Contexto

O produto quer um “foguinho” no estilo Duolingo/Snapchat para incentivar constância, mas atividades físicas exigem
descanso e planejamento. Uma ofensiva diária poderia estimular treino diário sem maturidade.

### Decisão

- A semana vai de segunda a domingo no fuso `America/Sao_Paulo`, identificada pela data da segunda-feira.
- A ofensiva semanal é a única mecânica de constância da V1.
- A primeira atividade válida da semana concede `WEEKLY_START` (+10 XP) e mantém o foguinho aceso.
- A segunda atividade concede `WEEKLY_GOAL` (+20 XP + `5 × min(semanas − 1, 6)`, com no máximo 50 XP no total) e completa a meta.
- Uma semana com 1 atividade mantém o foguinho; uma semana com 0 apaga.
- O recorde (`best`) é preservado.
- Não existe ofensiva diária nem bônus de primeira atividade do dia.
- O escudo/freeze fica para a V1.1.

### Alternativas consideradas

- Ofensiva diária: mais parecida com Duolingo, mas estimula treino diário e quebra com facilidade.
- Exigir 2 atividades para manter o foguinho: mais rigoroso, porém pouco tolerante a semanas atípicas.
- Ofensiva mensal: menos sensível ao engajamento semanal.

### Consequências

- A UI precisa mostrar foguinho atual, recorde, meta e estado (`ACESO`, `EM_RISCO`, `COMPLETO`, `APAGADO`).
- A ofensiva é recalculada após validação tardia e estorno.
- Notificações de risco dependem da Fase 7.

---

## ADR-035 — Deleveling e piso no nível 2

### Contexto

O `DEC03` deixava ambíguo se os 2% incidiam sobre a barra, sobre o XP do nível ou sobre o saldo absoluto. Era preciso
uma regra previsível, idempotente e que não destruísse o progresso do usuário.

### Decisão

- A tolerância é de 7 dias completos desde a última validação de presença.
- Depois da tolerância, cada 2 dias completos sem atividade válida reduzem 2% da barra do nível atual.
- Ao cruzar 0%, o usuário cai um nível e a barra do novo nível começa em 98%.
- O piso é o nível 2; o saldo nunca desce abaixo de `T(2) = 100 XP`.
- O XP vitalício não é afetado.
- A aplicação é idempotente por usuário e período.
- Vários períodos pendentes são aplicados em sequência.
- Uma nova atividade interrompe o deleveling, mas não devolve o XP perdido.
- O scheduler diário executa `ApplyInactivityDecayMessage`.

### Alternativas consideradas

- 2% do saldo absoluto: simples, mas acelera a perda quanto maior o saldo e fica imprevisível.
- Perda fixa de XP por período: fácil de entender, mas não escala com o nível.
- Não ter deleveling: contraria o requisito.

### Consequências

- A regra fica testada com a curva triangular e com múltiplos períodos.
- O deleveling pode reduzir o nível projetado em `users.level`, mas não remove grupos, recompensas ou vouchers.
- O scheduler precisa de lock e chave única por período.

---

## ADR-036 — Nível como projeção de Identity

### Contexto

Groups e a segurança usam `users.level` para autorização. A gamificação precisa calcular XP, bônus e deleveling. Mover
o nível completamente para Gamification exigiria alterar Groups, segurança e respostas existentes.

### Decisão

- `user_progress.level` é a fonte de verdade.
- `users.level` é uma projeção mantida por um listener de `LevelReached`/`LevelLost`.
- `User::completeOnboarding()` não incrementa mais o nível diretamente; o onboarding concede 100 XP e o nível 2 vem
  da gamificação.
- `GET /users/me` e `UserLevelReaderInterface` continuam lendo `users.level`.
- Um comando `app:gamification:check-level-projection` compara as duas fontes e, com `--repair`, corrige a projeção.
- `app:gamification:audit-ledger` compara o ledger com `user_progress` e com `users.level`; o `--repair` também
  alinha a projeção.

### Alternativas consideradas

- Mover o nível de vez para Gamification: elimina a dupla fonte, mas obriga a alterar Groups e segurança.
- Fazer `UserLevelReaderInterface` ler de Gamification: cria dependência de leitura cross-module e não resolve a
  autorização existente.
- Manter o nível só em Identity: impede a gamificação de calcular deleveling e bônus com autonomia.

### Consequências

- A operação normal é consistente porque os eventos são síncronos e transacionais.
- A dívida é a dupla fonte: um listener falho ou um caminho que altere o nível sem publicar evento pode divergir.
  A divergência agora é detectada e reparada explicitamente; o reparo usa uma porta de escrita e não publica
  `LevelReached`/`LevelLost`, porque corrige a materialização, não concede XP.
- O piso do nível 2 garante que Groups não perca acesso por deleveling.
- A dívida está registrada em `implementacao.md`; os gatilhos para pagá-la são necessidade de XP em Identity ou
  regras de nível dinâmicas.

---

## ADR-037 — Notificações multicanal com inbox, canais e Mercure

### Contexto

O produto precisa de um sino no frontend, mas a notificação não pode ficar presa a ele: o app nativo e canais
futuros (WhatsApp, push) devem consumir o mesmo fato. Ao mesmo tempo, denúncias e bloqueios foram adiados, então a
Fase 7 se concentra em notificações e administração de contas.

### Decisão

- `Notification` é o registro canônico: tipo estável, categoria, título, corpo PT-BR, `data` estruturado, `readAt`
  e chave de idempotência.
- O canal interno é o próprio registro; canais externos são adaptadores (`NotificationChannelInterface`) com
  `NotificationDelivery` por canal, status, tentativas e último erro.
- A criação é assíncrona via Messenger; falha de notificação não reverte a operação de negócio.
- O catálogo da V1 cobre grupos, atividades, gamificação e conta; e-mail é usado apenas para suspensão/reativação.
- Leitura é `readAt` + filtro `all|unread|read`; o frontend oculta as lidas, sem arquivamento/exclusão na V1.
- Mercure publica atualizações privadas no tópico do usuário; ver ADR-040.

### Alternativas consideradas

- Notificação apenas em tabela lida pelo frontend: simples, mas não atende app nativo nem canais externos.
- Usar somente Symfony Notifier: útil para canais, mas não resolve inbox, leitura, idempotência e deep link.
- Notificar todos os eventos de atividade e XP: gera ruído e foi rejeitado no catálogo.

### Consequências

- O worker passa a ser dependência para criação/entrega; mensagens ficam na fila se ele parar.
- O catálogo é um contrato estável (`NotificationType`); novos tipos exigem requisito e teste.
- Sem preferências por usuário na V1; a política é fixa por tipo.
- Sem retenção/limpeza automática na V1; dívida registrada em `implementacao.md`.
- Testes: `NotificationTest`, `NotificationPlannerTest`, `NotificationChannelPolicyTest`,
  `DoctrineNotificationRepositoryTest` e `ApiDocumentationTest`.

---

## ADR-038 — Denúncias e bloqueios fora da V1

### Contexto

O levantamento inicial previa denúncias (RF16) e bloqueio entre usuários (RF17). A decisão de produto é que a V1
tenha apenas administração global de contas, sem fila de denúncias nem bloqueio. O tema pode voltar quando houver
necessidade e estrutura de moderação.

### Decisão

- RF16 e RF17 ficam **adiados** na V1, mantendo os identificadores para rastreabilidade.
- Não será criado um módulo `Moderation` na V1; a administração de contas fica em `Identity`, dono de
  `users.status`, do histórico e dos refresh tokens.
- A moderação global se limita a listar/buscar contas, suspender e reativar (ADR-039).
- O tema deve ser reavaliado futuramente; se voltar, exige novo requisito, catálogo de denúncias e política de
  bloqueio.

### Alternativas consideradas

- Manter denúncias sem tratamento: cria fila sem operação e sem dono.
- Criar `Moderation` apenas para suspensão: adiciona indireção e transação entre módulos sem ganho.
- Manter bloqueio entre usuários: exigiria definir efeitos em solicitações, perfis e atividades.

### Consequências

- README, requisitos e arquitetura deixam de anunciar denúncias/bloqueios como escopo da V1.
- A tag `Usuários` e o grupo `Administração` cobrem a gestão global de contas.
- `DEC05` fica encerrado.

---

## ADR-039 — Suspensão global de contas

### Contexto

Conta suspensa já era bloqueada pelo `ActiveUserChecker`, mas o motivo não era persistido e não havia endpoint
administrativo. A V1 precisa de suspensão e reativação auditáveis, sem transformar suspensão em exclusão.

### Decisão

- Apenas `ADMIN_SIGAME` suspende e reativa; contas `ADMIN_SIGAME` não podem ser suspensas pela API.
- A suspensão exige motivo público (3–500 caracteres); a reativação aceita motivo opcional.
- `user_suspensions` é append-only com usuário, autor, ação, motivo e data.
- Suspender revoga todas as famílias de refresh tokens; o access token existente deixa de valer pelo
  `ActiveUserChecker`.
- A suspensão é indefinida até reativação manual; não há auto-reativação na V1.
- Grupos, participações, inscrições, XP, recompensas e vouchers são preservados.
- Suspender conta já suspensa ou reativar conta ativa é no-op idempotente.
- A aprovação de solicitação de usuário suspenso é recusada com `GROUP_MEMBERSHIP_SUSPENDED`.
- A provisão de `ADMIN_SIGAME` é feita por CLI (`app:identity:grant-admin`/`revoke-admin`), não por HTTP.

### Alternativas consideradas

- Suspensão com prazo e auto-reativação: adiciona scheduler e testes de tempo sem necessidade na V1.
- Remover o usuário de grupos e cancelar inscrições: confunde suspensão com exclusão.
- Permitir suspender outro admin: aumenta risco de lockout; contas privilegiadas ficam para operação manual.

### Consequências

- O histórico de status é auditável e não editável.
- A suspensão do único admin de um grupo pode deixar o grupo sem gestão; limitação registrada no `DEC15`.
- O motivo é público ao titular; notas internas exigirão campo próprio no futuro.
- Testes: `AdminUserTest`, `SuspendUserHandlerTest` e `DoctrineUserSuspensionRepositoryTest` (quando adicionado).

---

## ADR-040 — Mercure como transporte em tempo real do sino

### Contexto

O sino precisa refletir novas notificações e leituras em tempo real, inclusive em múltiplos dispositivos e no
futuro app nativo. O template já sobe um hub Mercure no Caddy, mas a aplicação ainda não o usava.

### Decisão

- O canal interno continua sendo a API; Mercure é apenas o transporte SSE.
- Cada usuário tem um tópico privado `.../notifications/{userId}`.
- `GET /api/v1/notifications/realtime` devolve `subscribeUrl`, token de curta duração e expiração.
- O token concede apenas `subscribe` no tópico do usuário; publicação é exclusiva do servidor.
- O cliente usa `Authorization: Bearer` no SSE (fetch-event-source ou equivalente); não há token na URL.
- A publicação ocorre após o commit e é best-effort; falha do hub não desfaz a notificação.
- O hub é usado em protocolo legado 0.x enquanto o Caddy estiver com `protocol_version_compatibility 8`.

### Alternativas consideradas

- Polling apenas: mais simples, mas pior latência e mais requisições.
- Cookie de assinatura: funciona em same-site, mas é frágil em frontend separado e app nativo.
- Mercure como canal de notificação: confunde transporte com persistência e perde leitura/idempotência.

### Consequências

- O bundle Mercure passa a ser dependência e exige `MERCURE_URL`, `MERCURE_PUBLIC_URL`, `MERCURE_JWT_SECRET`,
  `MERCURE_TOPIC_BASE` e `MERCURE_SUBSCRIBER_TOKEN_TTL`.
- O CORS do hub precisa permitir a origem do frontend.
- A revogação do token é por expiração; a conexão SSE pode sobreviver por alguns minutos após suspensão.
- Testes: `MercureRealtimePublisherTest`, `MercureStreamAuthorizationProviderTest` e `NotificationTest`.

---

## ADR-041 — Catálogo de regras de gamificação pela API

### Contexto

O front-end precisa exibir a curva de níveis, as fontes de XP, a fórmula de atividade, os bônus semanais e o
deleveling. O progresso (`GET /users/me/progress`) já entrega o estado do jogador e o histórico
(`/progress/history`) entrega o que foi concedido, mas não existe uma fonte para as regras. Reconstruí-las no
front duplicaria os valores de `GamificationRules` e faria a interface divergir do servidor em cada ajuste.

### Decisão

- Expor `GET /api/v1/gamification/rules`, autenticado com `ROLE_USER`, reutilizando o rate limit
  `gamification_read`.
- O payload é único, atômico em relação a `ruleVersion`, e gerado a partir do domínio (`LevelCurve`,
  `ActivityXpTable`, `WeeklyXpPolicy`, `GamificationRules`); nada é digitado no controller.
- Rótulos PT-BR (`label`/`description`) são enviados como *hint* de exibição; o cliente pode substituí-los.
- `ruleVersion` é o `ETag`; a resposta usa `Cache-Control: private, max-age=3600` e responde 304 por
  `If-None-Match`.
- O contrato é aditivo: novos campos podem aparecer sem mudança de URL; mudanças de valor exigem bump de
  `GamificationRules::VERSION`.
- O simulador de XP fica para a V1.1; na V1 o cliente exibe a faixa ("até 100 XP") e o valor real vem do
  histórico.

### Alternativas consideradas

- Hardcode no front: duplica as regras e diverge a cada ajuste.
- Bloco `rules` dentro de `/users/me/progress`: mistura dado imutável com estado do usuário e impede cache.
- Um endpoint por seção (`/levels`, `/xp-sources`, ...): múltiplas chamadas e risco de versões diferentes.
- GraphQL: fora do escopo da V1.

### Consequências

- A tela inicial de gamificação faz duas chamadas (progresso + catálogo); o catálogo fica cacheado por
  `ruleVersion`.
- `ActivityXpPolicy` passa a consumir `ActivityXpTable`, a mesma fonte usada pelo catálogo; um teste de
  tabela garante que o valor exibido e o concedido não divirjam.
- Testes: `ActivityXpTableTest`, `GamificationRulesTest` e `ApiDocumentationTest`.

---

## DEC09 — Backend em Symfony

### Contexto

A especificação inicial mencionava alternativas de backend e o documento de arquitetura chegou a citar "Nitro".

### Decisão

O backend da V1 é uma **API Symfony** (PHP 8.5 + Symfony 8.1), separada do front-end.

### Consequências

- O front-end consome a API por HTTP.
- A arquitetura, os testes e o deploy seguem o ecossistema Symfony.
- A decisão anterior sobre "Nitro" está encerrada.

---

## DEC13 — CPF opcional no cadastro e write-once

### Contexto

O CPF era originalmente obrigatório e editável na especificação inicial.

### Decisão

O CPF passa a ser opcional no cadastro, único quando informado e write-once. É exigido apenas para recompensas/vouchers externos.

### Consequências

- Requisitos `RF01`, `RN01.9`, `RN01.12` e `RF20` refletem a regra.
- A decisão técnica correspondente está em [ADR-005](#adr-005--cpf-opcional-e-write-once).

---

## DEC14 — Login social Google nesta entrega

### Contexto

O produto prevê login social, mas a V1 precisa limitar o escopo e ainda considerar um app nativo no futuro.

### Decisão

Implementar **apenas Google**, pelo fluxo de authorization code. Apple fica como evolução. A interface `SocialIdentityProviderInterface` e o registry permitem adicionar provedores sem alterar o handler, o banco ou o controller.

### Consequências

- `SOCIAL_PROVIDER_UNSUPPORTED` é retornado para provedores diferentes de `google`.
- O fluxo por `id_token` para app nativo fica preparado, mas não implementado.
- O login usa `start` + `callback` com state e PKCE, conforme o [ADR-012](#adr-012--fluxo-oauth-com-state-e-pkce-no-backend).
- O vínculo automático por e-mail só ocorre para contas sem senha local; as demais exigem vínculo explícito, conforme o [ADR-011](#adr-011--e-mail-canônico-e-vínculo-explícito-de-identidade-social).
- A configuração do Google está em [autenticacao.md](autenticacao.md).

---

## ADR-019 — Agregado de grupos com episódios de participação

### Contexto

O módulo Groups precisa representar grupos pequenos, com no máximo cinco membros aprovados, solicitações pendentes, rejeições, remoções auditáveis e designação opcional de supervisor. Os requisitos exigem registrar autor, membro, grupo, data e motivo na remoção (`CA06.5`) e impedir solicitações duplicadas (`RN05.2`).

### Decisão

Modelar `Group` como raiz de agregado e `Membership` como entidade interna. Cada solicitação/entrada gera um **episódio próprio**, com estados `PENDING`, `APPROVED`, `REJECTED` e `REMOVED`. Rejeição e remoção preservam a linha; uma nova entrada cria outro episódio.

- O estado do grupo (`FORMING`, `ACTIVE`, `FULL`) é **derivado** da contagem de membros aprovados, sem coluna persistida.
- Os limites ficam em `GroupPolicy`: 3 aprovados para ativar, 5 para lotar e 3 grupos por usuário.
- Índices parciais garantem no banco uma única participação ativa por par grupo/usuário e um único supervisor aprovado.
- Aprovações e decisões usam transação e lock pessimista no grupo; o limite de grupos por usuário usa advisory lock por `user_id`.

### Alternativas consideradas

- Uma única linha por par grupo/usuário com sobrescrita de status: simples, mas perde o histórico exigido pela auditoria.
- Tabela de auditoria separada: preserva histórico, porém duplica o ciclo de vida e adiciona escrita extra.
- Persistir o estado do grupo: acelera filtros, mas cria risco de divergência com a contagem real.

### Consequências

- Reentrada após rejeição ou remoção cria um novo episódio e mantém os anteriores.
- O histórico de membros removidos é consultável por administradores.
- Filtros por estado usam subquery de contagem; se o volume crescer, avaliar coluna denormalizada.
- Testes: `GroupTest`, `DoctrineGroupRepositoryTest`, `GroupMembershipTest`.

---

## ADR-020 — Consulta síncrona de nível entre módulos

### Contexto

Groups precisa validar que o usuário tem nível 2 ou superior para criar grupo (`RN04.1`). O módulo não pode importar `Identity\UserId` nem `UserRepositoryInterface`, porque as fronteiras são verificadas por `ModuleBoundariesTest`. O ADR-002 restringe a comunicação cross-module a Domain Events, mas eventos são assíncronos e não servem para uma validação que precisa acontecer na mesma requisição.

### Decisão

Declarar a porta `Shared\Application\User\UserLevelReaderInterface` (`levelOf(string $userId): ?int`) e implementá-la em `Identity\Infrastructure\User\DoctrineUserLevelReader`. Groups importa apenas a interface do kernel; Identity importa apenas Shared e seus próprios tipos.

### Alternativas consideradas

- Read model de nível em Groups atualizado por eventos: introduz consistência eventual e pode autorizar criação indevida com nível defasado.
- Consulta direta à tabela `users`: acopla Groups ao schema de Identity e ignora as regras do agregado.
- Enviar o nível no JWT: fica defasado e não reflete deleveling.

### Consequências

- Esta é uma **exceção documentada** ao “somente Domain Events”: vale para leituras síncronas de validação, não para efeitos colaterais.
- Novas consultas cross-module devem seguir o mesmo padrão: interface no `Shared`, adapter no módulo dono do dado.
- Testes: `CreateGroupHandlerTest` cobre nível 1, nível 2 e usuário inexistente.

---

## ADR-021 — Campos, visibilidade e busca de grupos

### Contexto

Os requisitos descrevem grupos esportivos e um link externo de comunicação (RF08), mas não detalham os campos do grupo nem como usuários descobrem grupos para solicitar entrada.

### Decisão

O grupo tem `name` (3–80), `description` opcional (até 500), `modality` (caminhada, corrida ou ciclismo) e `communicationLink` opcional (http/https, até 2048).

- Qualquer usuário autenticado pode listar e detalhar grupos com nome, descrição, modalidade, estado e contagem.
- O `communicationLink` só é retornado para membro aprovado (`RN08.2`).
- A listagem é paginada (máximo 30 por página) e aceita filtros por modalidade, estado, nome e disponibilidade.
- Não há chat próprio na V1 (`RN08.1`) nem exigência de telefone (`RN08.3`).

### Alternativas consideradas

- Exigir participação para ver o grupo: dificultaria a descoberta e o RF05.
- Deixar o link visível a todos: violaria o RF08.2.
- Não incluir modalidade no grupo: reduziria a utilidade dos filtros e a integração futura com Activities.

### Consequências

- A matriz de visibilidade de perfil (`DEC06`) segue aberta, mas a listagem não expõe dados pessoais.
- A busca usa `LOWER(name) LIKE`; para volume maior, avaliar índice textual.
- Testes: `GroupTest` cobre criação, listagem, filtros e visibilidade do link.

---

## ADR-022 — Supervisor sem poderes de gestão na V1

### Contexto

O `DEC11` deixava em aberto as permissões exatas do supervisor. A matriz inicial permite ao supervisor criar, remarcar e cancelar atividades e validar presença, mas não aprovar entrada nem remover membros.

### Decisão

Na Fase 4, supervisor é apenas um membro aprovado marcado com `is_supervisor`. Ele **não** aprova, rejeita, remove, promove ou rebaixa membros e não altera o grupo. Os poderes de atividade serão implementados e testados na Fase 5, quando Activities existir.

- A designação é feita por qualquer administrador e é opcional.
- Só um membro aprovado pode ser supervisor; apenas um supervisor por grupo.
- A remoção do membro limpa automaticamente a designação.

### Alternativas consideradas

- Delegar aprovação e remoção ao supervisor: contraria a matriz de permissões e amplia o risco de abuso.
- Fechar agora todos os poderes de atividade: anteciparia decisões de Activities sem o agregado existir.

### Consequências

- `DEC11` fica **fechado**: o ADR-027 define os poderes de atividade do supervisor.
- Testes: `GroupTest`, `GroupMembershipTest` e `DoctrineGroupRepositoryTest` cobrem a designação e a ausência de poderes de gestão.

> **Emenda (ADR-027):** o supervisor pode iniciar, finalizar, validar e listar presenças, mas não cria, edita, remarca, cancela ou invalida atividades.

---

## ADR-023 — Criador e administradores independentes

### Contexto

O criador do grupo precisa continuar registrado mesmo que deixe de ser administrador. O produto exige mais de um administrador por grupo, e o papel de admin não pode ser derivado do campo de criador.

### Decisão

- `groups.created_by` registra o criador, é imutável e não concede autorização por si só.
- O criador inicia como `ADMIN`, mas o papel vive em `Membership.role`.
- O grupo pode ter vários administradores aprovados; qualquer admin pode promover ou rebaixar um membro aprovado.
- O grupo nunca fica sem ao menos um admin: rebaixar ou remover o último admin retorna `GROUP_LAST_ADMIN_REQUIRED`.
- A promoção/rebaixamento é exposta por `PATCH /groups/{id}/memberships/{membershipId}/role`.

### Alternativas consideradas

- Admin único derivado do criador: não permite delegação nem substituição.
- Transferência de propriedade do grupo: adiciona um fluxo que os requisitos não pedem.
- Permitir grupo sem admin: cria estado impossível para as operações de gestão.

### Consequências

- `createdBy` permanece no payload e no banco como referência histórica.
- Um admin rebaixado perde imediatamente as operações de gestão.
- A migration não cria índice único de administrador; a invariante é garantida pelo domínio e pelo lock.
- Testes: `GroupTest`, `ChangeMembershipRoleHandlerTest` e `GroupMembershipTest`.

---

## ADR-042 — Limite de membros configurável por grupo

### Contexto

O ADR-019 fixou o limite de cinco membros aprovados em `GroupPolicy`. A evolução do produto exige que cada grupo
escolha entre um limite explícito de pessoas ou um grupo ilimitado, mantendo a ativação com três aprovados. O limite
não deve ser confundido com a quantidade de participantes de uma atividade, que permanece sem teto próprio.

### Decisão

- `groups.max_members` é um inteiro nullable; `NULL` representa grupo ilimitado.
- Grupos existentes e novos grupos sem o campo ficam ilimitados.
- Quando informado, o limite mínimo é 3, alinhado ao mínimo de membros para o grupo ser ativo.
- `PATCH /groups/{id}` aceita `maxMembers` numérico para definir o limite e `null` para removê-lo. O campo ausente não
  altera o valor atual.
- Reduzir o limite abaixo da quantidade atual de aprovados retorna `GROUP_MEMBER_LIMIT_BELOW_APPROVED`.
- `GroupStatus::FULL` passa a depender do limite: grupos ilimitados nunca ficam cheios, e grupos limitados ficam
  cheios ao atingir o limite.
- `updateGroup()` usa lock pessimista no grupo para serializar a alteração de limite com aprovações concorrentes.

### Alternativas consideradas

- Manter o limite fixo de cinco membros: não atende à possibilidade de grupo ilimitado.
- Usar `0` como sentinela de ilimitado: mistura valor inválido com estado de domínio e dificulta a validação.
- Limitar a alteração apenas na criação: impede ajustes conforme a rotina do grupo muda.

### Consequências

- O limite deixa de ser uma constante global e passa a ser um dado do agregado.
- O filtro `availableOnly` e os filtros por `status` comparam a contagem de aprovados com `max_members`.
- A migration adiciona a coluna sem backfill; grupos existentes permanecem ilimitados.
- Testes de domínio, handler, repositório, tipo Doctrine e HTTP cobrem limite definido, removido e abaixo do total
  aprovado.
