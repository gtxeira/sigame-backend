# SIGAME — Infraestrutura

> **Última atualização:** 2026-11-05
>
> **Público:** pessoas que vão subir, configurar ou implantar a aplicação.

Este documento descreve os serviços, portas, variáveis de ambiente e o caminho de produção. Para o fluxo de desenvolvimento, veja [desenvolvimento.md](desenvolvimento.md).

## 1. Visão geral

O runtime é baseado em **FrankenPHP** (PHP 8.5 + Caddy) dentro de Docker. O Compose é dividido em três arquivos:

| Arquivo | Papel |
|---|---|
| `compose.yaml` | Base: serviço `php` e volumes do Caddy |
| `compose.override.yaml` | Desenvolvimento: build `frankenphp_dev`, bind mount do código, Xdebug, PostgreSQL e Garage |
| `compose.prod.yaml` | Produção: imagem `frankenphp_prod` e segredos de runtime |

O `justfile` carrega `.env`, `.env.dev` e `.env.local` automaticamente.

## 2. Serviços e portas

### 2.1 Desenvolvimento

| Serviço | Imagem / build | Portas | Observações |
|---|---|---|---|
| `php` | `frankenphp_dev` | `8000/tcp` (HTTP), `8443/tcp` (HTTPS), `8443/udp` (HTTP/3) | Código montado em `/app`; worker com watch/hot reload |
| `postgres` | `postgres:18-alpine` | `5432` | Volume `pgdata`; padrão `POSTGRES_DB=sigame` |
| `garage` | `dxflrs/garage:v2.4.1` | `3900` (S3 API), `3902` (Web) | Configuração em `garage.toml`; volumes `garage-meta` e `garage-data` |

O entrypoint espera o PostgreSQL responder e aplica as migrations antes de liberar a aplicação.

### 2.2 Produção

O `compose.prod.yaml` substitui o build do `php` por `frankenphp_prod`. Ele **não** define PostgreSQL nem Garage: em produção, banco e storage devem ser serviços externos (ou um Compose separado), configurados por `DATABASE_URL` e `S3_*`.

O Dockerfile de produção:

- instala dependências sem `--dev`;
- gera o autoloader com `--classmap-authoritative`;
- roda `composer dump-env prod`;
- habilita OPcache sem validação de timestamps e com preload;
- usa o entrypoint para aplicar migrations pendentes.

## 3. Variáveis de ambiente

Nunca coloque segredos reais em `.env`. Use `.env.local` (ignorado pelo Git) ou o cofre de segredos do Symfony.

### 3.1 Aplicação e servidor

| Variável | Padrão | Descrição |
|---|---|---|
| `APP_ENV` | `dev` | Ambiente do Symfony |
| `APP_DEBUG` | `1` em dev | Liga o modo debug |
| `APP_SECRET` | — | Segredo do framework |
| `SERVER_NAME` | `api.sigame.localhost` | Domínio usado pelo Caddy e pelo TLS |
| `DEFAULT_URI` | `https://${SERVER_NAME}:${HTTPS_PORT}` | URI padrão para gerar URLs absolutas |
| `HTTP_PORT` | `8000` | Porta HTTP publicada |
| `HTTPS_PORT` | `8443` | Porta HTTPS publicada |
| `HTTP3_PORT` | `8443` | Porta UDP do HTTP/3 |
| `CORS_ALLOW_ORIGINS` | `https://sigame.com.br` | Regex de origens permitidas com credenciais |
| `CADDY_MERCURE_JWT_SECRET` | — | Segredo do hub Mercure do template |

### 3.2 Banco de dados

| Variável | Padrão | Descrição |
|---|---|---|
| `POSTGRES_USER` | `sigame` | Usuário do PostgreSQL |
| `POSTGRES_PASSWORD` | `!ChangeMe!` | Senha do PostgreSQL |
| `POSTGRES_DB` | `sigame` | Banco principal |
| `POSTGRES_VERSION` | `18` | Versão usada na URL do Doctrine |
| `DATABASE_URL` | `postgresql://...@postgres:5432/sigame?...` | URL completa do Doctrine |

O ambiente de teste cria um banco com sufixo `_test`; o ambiente de desenvolvimento usa o banco principal.

### 3.3 Autenticação

| Variável | Padrão | Descrição |
|---|---|---|
| `JWT_PASSPHRASE` | — | Passphrase usada pelo fluxo de chaves, quando aplicável |
| `ACCESS_TOKEN_SECRET` | — | Segredo HS256 com pelo menos 32 caracteres |
| `ACCESS_TOKEN_ISSUER` | `sigame` | Claim `iss` |
| `ACCESS_TOKEN_AUDIENCE` | `sigame-api` | Claim `aud` |
| `ACCESS_TOKEN_TTL` | `900` | Validade do access token em segundos |
| `REFRESH_TOKEN_TTL` | `604800` | Validade do refresh token em segundos |
| `REFRESH_TOKEN_REUSE_GRACE_SECONDS` | `30` | Janela de graça da rotação |
| `REFRESH_TOKEN_MAX_FAMILIES` | `5` | Máximo de sessões ativas por usuário |
| `REFRESH_COOKIE_NAME` | `refresh_token` | Nome do cookie |
| `REFRESH_COOKIE_PATH` | `/api/v1/auth` | Path do cookie |
| `REFRESH_COOKIE_SECURE` | `1` | Exige HTTPS |
| `REFRESH_COOKIE_SAMESITE` | `lax` | `lax`, `strict` ou `none` |
| `AUTH_LOGIN_RATE_LIMIT` | `5` | Tentativas de login por minuto |
| `AUTH_SOCIAL_RATE_LIMIT` | `10` | Tentativas de login social por minuto |
| `AUTH_REFRESH_RATE_LIMIT` | `30` | Renovações por minuto |
| `USER_CPF_RATE_LIMIT` | `10` | Definições de CPF por minuto |
| `JWKS_CACHE_TTL` | `3600` | Cache do JWKS do provedor social |

### 3.4 Login social

| Variável | Padrão | Descrição |
|---|---|---|
| `GOOGLE_CLIENT_ID` | — | Client ID do Google Cloud |
| `GOOGLE_CLIENT_SECRET` | — | Client secret do Google Cloud |
| `GOOGLE_REDIRECT_URIS` | `https://sigame.com.br/login/google/callback` | Allowlist exata de redirect URIs |

Detalhes do fluxo e do Playground estão em [autenticacao.md](autenticacao.md).

### 3.5 Armazenamento S3

| Variável | Padrão | Descrição |
|---|---|---|
| `S3_ENDPOINT` | `http://localhost:3900` | Endpoint S3 (Garage local ou provedor externo) |
| `S3_REGION` | `us-east-1` | Região do bucket |
| `S3_ACCESS_KEY` | — | Access key |
| `S3_SECRET_KEY` | — | Secret key |
| `S3_BUCKET` | `profile-images` | Bucket de arquivos |
| `S3_PUBLIC_URL` | `http://${S3_BUCKET}.localhost:3902` | URL pública usada nos exemplos |

O Garage local usa `garage.toml`:

- API S3 em `:3900`;
- interface web em `:3902`;
- RPC em `:3901`;
- admin em `:3903` (não publicado no Compose);
- `replication_factor = 1` e banco SQLite, adequados apenas para desenvolvimento.

> O valor de `S3_ENDPOINT` em `.env` usa `localhost:3900`, pensado para acesso a partir do host. Dentro do container `php`, o endpoint precisa apontar para o serviço (`http://garage:3900`); ajuste em `.env.local` quando o adapter de storage for implementado.

> A aplicação ainda **não** implementa o adapter de storage. Os pacotes estão instalados, mas o upload de foto permanece pendente.

### 3.6 Lock

| Variável | Padrão | Descrição |
|---|---|---|
| `LOCK_DSN` | `flock` | Store usado pelo `symfony/lock` |

Em produção com múltiplas instâncias, prefira um store compartilhado (por exemplo, `postgresql+advisory://...`) em vez de `flock`.

## 4. Migrations e inicialização

O entrypoint (`frankenphp/docker-entrypoint.sh`):

1. instala dependências se `vendor/` estiver vazio;
2. exibe a versão do Symfony;
3. espera o banco responder;
4. executa `doctrine:migrations:migrate --no-interaction --all-or-nothing` se houver migrations.

Comandos úteis:

```bash
just sf doctrine:migrations:status
just sf doctrine:migrations:migrate --no-interaction
just sf doctrine:migrations:diff
```

Nunca use `doctrine:schema:update` para alterar o schema. O histórico e as regras estão em [implementacao.md](implementacao.md#4-migrações-do-banco-de-dados).

## 5. Health checks

A aplicação expõe:

| Rota | Uso |
|---|---|
| `GET /api/health/liveness` | Verifica se o processo responde |
| `GET /api/health/readiness` | Verifica se a aplicação está pronta, incluindo o banco |

O Dockerfile também usa o endpoint interno de métricas do Caddy (`:2019/metrics`) como healthcheck do container.

## 6. TLS e domínios

Em desenvolvimento, o Caddy gera um certificado autoassinado para `api.sigame.localhost`. Aceite o certificado no navegador.

Em produção:

- use um domínio real em `SERVER_NAME`;
- exponha 80/443 (ou deixe um proxy reverso terminar o TLS);
- ajuste `DEFAULT_URI` e `CORS_ALLOW_ORIGINS`;
- mantenha `REFRESH_COOKIE_SECURE=1` e prefira `SameSite=strict` ou `lax` quando front e API forem same-site.

## 7. Segurança operacional

- `.env` contém apenas padrões; segredos reais vão em `.env.local` ou no cofre.
- `ACCESS_TOKEN_SECRET` e `APP_SECRET` devem ter pelo menos 32 caracteres e ser diferentes por ambiente.
- O cookie de refresh é `HttpOnly` e restrito a `/api/v1/auth`.
- O banco de produção deve ter backup e usuário com privilégios mínimos.
- O rate limiter precisa de storage compartilhado quando houver mais de uma instância.
- O hub Mercure e o Vulcain vêm do template, mas ainda não são usados pela aplicação.

## 8. Referências oficiais

- [Symfony Docker](https://github.com/dunglas/symfony-docker)
- [Documentação do Symfony Docker](https://github.com/dunglas/symfony-docker/tree/main/docs)
- [FrankenPHP](https://frankenphp.dev/docs/)
- [Symfony](https://symfony.com/doc/current/)
- [Doctrine Migrations](https://www.doctrine-project.org/projects/migrations.html)
