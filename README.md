# SIGAME

Plataforma web para incentivar a prática de atividades físicas em grupo: encontrar pessoas com interesses e disponibilidades compatíveis, formar pequenos grupos, organizar atividades, registrar presença e acompanhar a evolução de cada participante.

> **Status:** V1 em desenvolvimento. O módulo de identidade (cadastro, autenticação, conta e onboarding) está implementado. Os demais módulos estão no roadmap. O acompanhamento detalhado fica em [docs/implementacao.md](docs/implementacao.md).

## Visão do projeto

Praticar atividade física em grupo é mais do que organizar um horário: envolve encontrar pessoas compatíveis, sentir-se seguro para começar, manter constância e perceber progresso. Hoje essas necessidades estão espalhadas por redes sociais genéricas, grupos de mensagens e planilhas informais, que não foram desenhados para isso.

O SIGAME nasce para centralizar essa experiência. A proposta é oferecer um canal próprio para que pessoas encontrem grupos esportivos com interesses e disponibilidades parecidas, participem de atividades organizadas e acompanhem sua evolução com regras claras de presença, progresso e reconhecimento.

O produto prioriza **inclusão**: iniciantes, pessoas sedentárias, tímidas ou que nunca praticaram atividade em grupo devem se sentir acolhidas. Por isso, dados como peso e altura são opcionais e não impedem o acesso às funcionalidades sociais; não há exigência de GPS; e a comunicação do grupo pode acontecer por um link externo, sem expor telefone pessoal.

A V1 também leva a sério **segurança e moderação**. Denúncias, bloqueio entre usuários e suspensão global de conta fazem parte do escopo, assim como a separação entre administração da plataforma, administradores de grupo e supervisores. Nenhuma operação financeira, Pix, comissão ou repasse faz parte da V1.

As modalidades iniciais são **caminhada, corrida e ciclismo**. O usuário pode praticar mais de uma modalidade e definir uma delas como principal sem perder histórico ou XP.

## Problema a resolver

Redes sociais e aplicativos de mensagem não foram projetados para localizar grupos esportivos compatíveis. Eles ajudam a conversar, mas não ajudam a responder perguntas como:

- quem pratica a mesma modalidade, no mesmo nível e nos mesmos horários?
- como entrar em um grupo pequeno, com regras claras e moderação?
- como saber se uma atividade realmente aconteceu e quem participou?
- como acompanhar presença, progresso e reconhecimento ao longo do tempo?
- como um iniciante pode começar sem se sentir exposto ou deslocado?

Grupos informais costumam depender de convites, mensagens soltas e memória. Não há histórico confiável de presença, critério de progressão, controle de vagas ou mecanismo de segurança. O SIGAME centraliza essas necessidades em uma plataforma específica para prática esportiva coletiva:

- descoberta e entrada em grupos pequenos;
- organização de atividades com data, horário, ponto de encontro e trajeto;
- check-in individual e validação de presença;
- progressão por XP e níveis, com regras de deleveling por inatividade;
- notificações internas de mudanças relevantes;
- moderação, denúncias e bloqueios;
- patrocínios e vouchers vinculados ao grupo, sem transação financeira.

## Escopo da V1

**Incluído:**

- cadastro local e login social com Google;
- onboarding com modalidades e nível inicial;
- grupos de até 5 membros aprovados, com administrador e supervisor;
- atividades vinculadas a grupos ativos;
- check-in e validação de presença;
- gamificação com 10 níveis;
- notificações internas;
- denúncias, bloqueio e suspensão global;
- patrocínios, benefícios e vouchers;
- indicador mensal interno de desempenho dos grupos.

**Fora do escopo:**

- aplicativo mobile nativo;
- chat interno próprio;
- rastreamento GPS obrigatório;
- recebimento de dinheiro, Pix, comissão ou repasse financeiro.

## Stack

| Camada | Tecnologia |
|---|---|
| Runtime | PHP 8.5 |
| Framework | Symfony 8.1 |
| Web server | FrankenPHP 1 + Caddy |
| Banco | PostgreSQL 18 |
| ORM | Doctrine ORM 3 + Doctrine Migrations 4 |
| Autenticação | `lcobucci/jwt`, `firebase/php-jwt` e autenticador customizado |
| Serialização / validação | Symfony Serializer e Validator |
| Storage | Flysystem 3 + S3 (Garage local) |
| Testes | Pest 5 + Mockery + Foundry + DAMA |
| Qualidade | PHPStan 6, php-cs-fixer (`@Symfony`) e CaptainHook |
| Documentação da API | NelmioApiDocBundle + Scalar |

## Arquitetura em resumo

O backend é um **monolito modular** com Clean Architecture. Cada módulo funcional vive em `src/<Módulo>/` e organiza suas camadas como `Domain → Application → Infrastructure → Presentation`. Módulos não importam classes concretas uns dos outros; a comunicação acontece por Domain Events.

O módulo `Shared` funciona como kernel compartilhado (VOs, erros, eventos, persistência base) e não pode importar módulos funcionais. As fronteiras são verificadas por teste automatizado.

Detalhes de princípios, estrutura de diretórios, modelo de domínio e estratégia de testes estão em [docs/arquitetura.md](docs/arquitetura.md).

## Pré-requisitos

- Docker Engine com Docker Compose v2.10+
- [just](https://just.systems/) (recomendado) ou `docker compose` diretamente
- Opcional: [mise](https://mise.jdx.dev/) para instalar a versão fixada do `just`

## Quick start

```bash
# 0. Crie o arquivo de overrides local (pode começar vazio)
touch .env.local

# 1. Construir as imagens
just build

# 2. Subir os containers
just up

# 3. Acompanhar os logs
just logs
```

O `justfile` referencia `.env.local`; o arquivo é ignorado pelo Git e deve existir mesmo que vazio. Preencha os segredos nele quando necessário.

Sem `just`:

```bash
docker compose --env-file .env --env-file .env.dev --env-file .env.local up --detach
```

Na primeira subida, o entrypoint instala as dependências, espera o PostgreSQL e aplica as migrations.

### URLs locais

| Recurso | URL |
|---|---|
| API | <https://api.sigame.localhost:8443> |
| Documentação interativa | <https://api.sigame.localhost:8443/doc> |
| OpenAPI JSON | <https://api.sigame.localhost:8443/doc.json> |
| Readiness | <https://api.sigame.localhost:8443/api/health/readiness> |

O certificado TLS é autoassinado; aceite-o no navegador na primeira visita.

### Configuração

- `.env` — padrões versionados;
- `.env.dev` — padrões de desenvolvimento;
- `.env.local` — segredos e overrides locais, **não versionado**;
- `.env.test` — configuração da suíte de testes.

Para o login com Google, preencha `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` e `GOOGLE_REDIRECT_URIS` em `.env.local`. O passo a passo está em [docs/autenticacao.md](docs/autenticacao.md).

A lista completa de variáveis está em [docs/infraestrutura.md](docs/infraestrutura.md).

## Endpoints

Rotas de negócio usam `/api/v1`. Probes operacionais usam `/api/health`, sem versionamento.

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `POST` | `/api/v1/auth/register` | Pública | Cadastrar usuário |
| `POST` | `/api/v1/auth/login` | Pública | Autenticar com e-mail e senha |
| `POST` | `/api/v1/auth/social/{provider}/start` | Pública (Bearer opcional) | Iniciar o fluxo de login social |
| `POST` | `/api/v1/auth/social/{provider}/callback` | Pública + cookie | Concluir o login social |
| `POST` | `/api/v1/auth/social/{provider}/link` | Bearer JWT | Vincular conta social pelo ticket |
| `POST` | `/api/v1/auth/email/verify` | Pública | Verificar o e-mail |
| `POST` | `/api/v1/auth/email/verification/resend` | Bearer JWT | Reenviar verificação de e-mail |
| `POST` | `/api/v1/auth/password/forgot` | Pública | Solicitar redefinição de senha |
| `POST` | `/api/v1/auth/password/reset` | Pública | Redefinir a senha |
| `POST` | `/api/v1/auth/refresh` | Cookie | Renovar o par de tokens |
| `POST` | `/api/v1/auth/logout` | Cookie | Encerrar a sessão atual |
| `POST` | `/api/v1/auth/logout/all` | Bearer JWT | Encerrar todas as sessões |
| `GET` | `/api/v1/users/me` | Bearer JWT | Obter os dados do usuário autenticado |
| `PATCH` | `/api/v1/users/me/cpf` | Bearer JWT | Definir o CPF (write-once) |
| `POST` | `/api/v1/users/me/onboarding` | Bearer JWT | Concluir o onboarding e alcançar o nível 2 |
| `POST` | `/api/v1/users/me/password` | Bearer JWT | Alterar a senha |
| `POST` | `/api/v1/users/me/identities/{provider}` | Bearer JWT | Vincular identidade social |
| `GET` | `/api/health/liveness` | Pública | Verificar a execução da aplicação |
| `GET` | `/api/health/readiness` | Pública | Verificar a prontidão e o banco |

O contrato completo, com schemas e exemplos, é servido em `/doc`.

## Autenticação em resumo

- **Access token:** JWT HS256, 15 minutos, stateless, enviado em `Authorization: Bearer`.
- **Refresh token:** string opaca `rt_` + 64 hex, 7 dias, persistido apenas como SHA-256 e entregue em cookie `HttpOnly`.
- **Rotação:** cada renovação invalida o token anterior e cria outro na mesma família.
- **Detecção de reuso:** reapresentar um token revogado fora da janela de graça revoga toda a família.
- **Logout:** revoga a família atual; `/logout/all` revoga todas as sessões.

O front-end precisa chamar a API com credenciais habilitadas (`credentials: 'include'` no `fetch` ou `withCredentials` no axios).

Fluxos, exemplos de request/response, códigos de erro, rate limiting e configuração do Google estão em [docs/autenticacao.md](docs/autenticacao.md).

## Armazenamento de fotos

As fotos de perfil ficam no bucket S3 `profile-images`, com escrita pela credencial da aplicação e leitura pública pelo endpoint website do Garage local.

```bash
just storage-setup   # habilita leitura website do bucket no Garage
```

O upload aceita JPEG, PNG ou WebP de até 2 MB e 200×200 a 4096×4096. A referência persistida é a chave do objeto (`users/{id}/{uuid}.{ext}`), não a URL. Detalhes em [docs/infraestrutura.md](docs/infraestrutura.md#35-armazenamento-s3).

## Banco de dados e migrations

O schema é versionado por Doctrine Migrations. O entrypoint aplica migrations pendentes ao subir o container.

```bash
just sf doctrine:migrations:status
just sf doctrine:migrations:migrate --no-interaction
just sf doctrine:migrations:diff
```

Nunca use `doctrine:schema:update` para alterar o schema. O histórico das migrations e as regras de evolução estão em [docs/implementacao.md](docs/implementacao.md#4-migrações-do-banco-de-dados).

## Testes e qualidade

```bash
just test              # suíte completa
just test-unit         # entidades, VOs, policies e handlers
just test-functional   # HTTP: rotas, status, payload e autenticação
just test-integration  # repositórios e adaptadores reais
just cs-check          # padrão de código
just phpstan           # análise estática nível 6
just composer quality  # cs:check + phpstan + test
```

Os testes de banco rodam em transação com rollback (DAMA) e o schema vem das migrations (`Foundry reset.mode: migrate`). Detalhes e convenções estão em [docs/desenvolvimento.md](docs/desenvolvimento.md).

## Estrutura do projeto

```text
src/
├── Shared/          # kernel: VOs, erros, eventos, persistência base, HTTP comum
├── Monitoring/      # liveness, readiness e saúde das dependências
└── Identity/        # cadastro, autenticação, identidades e refresh tokens
    ├── Domain/
    ├── Application/
    ├── Infrastructure/
    └── Presentation/

tests/
├── Unit/            # grupo unit
├── Functional/      # grupo functional (HTTP)
├── Integration/     # grupo integration (adaptadores reais)
└── Helpers/         # ApiClient e factories de teste
```

Módulos planejados: `Groups`, `Activities`, `Gamification`, `Moderation`, `Sponsorship`, `Notifications` e `Reports`. O estado de cada um está em [docs/implementacao.md](docs/implementacao.md).

## Documentação

| Documento | Conteúdo |
|---|---|
| [docs/arquitetura.md](docs/arquitetura.md) | Princípios, módulos, camadas, modelo de domínio e testes |
| [docs/autenticacao.md](docs/autenticacao.md) | Tokens, endpoints, erros, rate limiting e Google |
| [docs/desenvolvimento.md](docs/desenvolvimento.md) | Ambiente local, comandos, testes, qualidade e debug |
| [docs/infraestrutura.md](docs/infraestrutura.md) | Serviços, portas, variáveis, S3/Garage e produção |
| [docs/api-documentation.md](docs/api-documentation.md) | Convenções de tags, summaries, segurança e versionamento da OpenAPI |
| [docs/implementacao.md](docs/implementacao.md) | Estado atual, roadmap, decisões abertas, migrations e dívidas técnicas |
| [docs/decisoes.md](docs/decisoes.md) | Registro do porquê das decisões arquiteturais e de produto |
| [docs/requisitos/levantamento-de-requisitos.md](docs/requisitos/levantamento-de-requisitos.md) | Requisitos funcionais, regras de negócio e critérios de aceitação |

## Contribuição

- Mensagens de commit seguem **Conventional Commits** (`feat`, `fix`, `docs`, `test`, `refactor`, `chore`…).
- O hook de pre-commit roda php-cs-fixer e PHPStan nos arquivos alterados.
- Antes de abrir um PR, rode `just composer quality`.

## Referências

- [Symfony](https://symfony.com/doc/current/)
- [Symfony Docker](https://github.com/dunglas/symfony-docker)
- [FrankenPHP](https://frankenphp.dev/docs/)
- [PostgreSQL](https://www.postgresql.org/docs/)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- [Pest](https://pestphp.com/)
