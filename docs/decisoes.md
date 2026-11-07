# SIGAME — Decisões arquiteturais e de produto

> **Status:** registro vivo
>
> **Última atualização:** 2026-11-05
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
| [ADR-002](#adr-002--comunicação-entre-módulos-por-domain-events) | Comunicação entre módulos por Domain Events | Vigente |
| [ADR-003](#adr-003--access-jwt--refresh-token-opaco-rotativo) | Access JWT + refresh token opaco rotativo | Vigente |
| [ADR-004](#adr-004--users--user_identities-1n) | `users` + `user_identities` 1:N | Vigente |
| [ADR-005](#adr-005--cpf-opcional-e-write-once) | CPF opcional e write-once | Vigente |
| [ADR-006](#adr-006--catálogo-de-erros-e-status-http-derivado) | Catálogo de erros e status HTTP derivado | Vigente |
| [ADR-007](#adr-007--testes-em-unit-functional-e-integration) | Testes em Unit, Functional e Integration | Vigente |
| [ADR-008](#adr-008--configuração-em-php-com-autowiring) | Configuração em PHP com autowiring | Vigente |
| [ADR-009](#adr-009--postgresql-18-com-doctrine-migrations) | PostgreSQL 18 com Doctrine Migrations | Vigente |
| [ADR-010](#adr-010--arquivos-em-s3-via-flysystemgarage) | Arquivos em S3 via Flysystem/Garage | Decidida; implementação pendente |
| [ADR-011](#adr-011--e-mail-canônico-e-vínculo-explícito-de-identidade-social) | E-mail canônico e vínculo explícito de identidade social | Vigente |
| [ADR-012](#adr-012--fluxo-oauth-com-state-e-pkce-no-backend) | Fluxo OAuth com state e PKCE no backend | Vigente |
| [ADR-013](#adr-013--tokens-de-uso-único-para-verificação-de-e-mail-e-reset-de-senha) | Tokens de uso único para verificação de e-mail e reset de senha | Vigente |
| [ADR-014](#adr-014--e-mails-de-identidade-assíncronos-com-payload-cifrado) | E-mails de identidade assíncronos com payload cifrado | Vigente |

Decisões de produto fechadas: [`DEC09`](#dec09--backend-em-symfony), [`DEC13`](#dec13--cpf-opcional-no-cadastro-e-write-once) e [`DEC14`](#dec14--login-social-google-nesta-entrega).

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

- **Implementação pendente**: os pacotes estão instalados, mas ainda não há `FileStorageInterface` nem adapter.
- As variáveis `S3_*` já existem em `.env`.
- O bucket, limites e política de acesso precisam ser definidos antes do upload.

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
