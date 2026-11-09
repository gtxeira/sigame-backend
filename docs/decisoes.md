# SIGAME — Decisões arquiteturais e de produto

> **Status:** registro vivo
>
> **Última atualização:** 2026-11-08
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
| [ADR-010](#adr-010--arquivos-em-s3-via-flysystemgarage) | Arquivos em S3 via Flysystem/Garage | Vigente |
| [ADR-011](#adr-011--e-mail-canônico-e-vínculo-explícito-de-identidade-social) | E-mail canônico e vínculo explícito de identidade social | Vigente |
| [ADR-012](#adr-012--fluxo-oauth-com-state-e-pkce-no-backend) | Fluxo OAuth com state e PKCE no backend | Vigente |
| [ADR-013](#adr-013--tokens-de-uso-único-para-verificação-de-e-mail-e-reset-de-senha) | Tokens de uso único para verificação de e-mail e reset de senha | Vigente |
| [ADR-014](#adr-014--e-mails-de-identidade-assíncronos-com-payload-cifrado) | E-mails de identidade assíncronos com payload cifrado | Vigente |
| [ADR-015](#adr-015--onboarding-como-ação-única-e-evento-de-domínio) | Onboarding como ação única e evento de domínio | Vigente (emendada pelo ADR-017) |
| [ADR-016](#adr-016--upload-de-foto-de-perfil-com-chave-em-s3) | Upload de foto de perfil com chave em S3 | Vigente |
| [ADR-017](#adr-017--perfil-parcial-e-onboarding-restrito-a-modalidades) | Perfil parcial e onboarding restrito a modalidades | Vigente |
| [ADR-018](#adr-018--foto-de-perfil-quadrada-em-512-no-cliente) | Foto de perfil quadrada em 512 no cliente | Vigente |

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
