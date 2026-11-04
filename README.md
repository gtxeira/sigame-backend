# Symfony Docker

A [Docker](https://www.docker.com/)-based installer and runtime for the [Symfony](https://symfony.com) web framework,
with [FrankenPHP](https://frankenphp.dev) and [Caddy](https://caddyserver.com/) inside!

Coding-agents ready: ships with a [Dev Container](https://containers.dev/) and a [one-page guide](docs/symfony-docker/agents.md)
to run [OpenCode](https://opencode.ai), [Claude Code](https://claude.ai/claude-code), or any AI coding assistant,
against a local or a remote model, with an optional network sandbox.

![CI](https://github.com/dunglas/symfony-docker/workflows/CI/badge.svg)

## Getting Started

1. If not already done, [install Docker Compose](https://docs.docker.com/compose/install/) (v2.10+)
2. Run `docker compose build --pull --no-cache` to build fresh images
3. Run `docker compose up --wait` to set up and start a fresh Symfony project
4. Open `https://localhost` in your favorite web browser and [accept the auto-generated TLS certificate](https://stackoverflow.com/a/15076602/1352334)
5. Run `docker compose down --remove-orphans` to stop the Docker containers.

## Features

- Production, development and CI ready
- Just 1 service by default
- Super-readable configuration
- Blazing-fast performance thanks to [the worker mode of FrankenPHP](https://frankenphp.dev/docs/symfony-docker/worker/)
- [Installation of extra Docker Compose services](docs/symfony-docker/extra-services.md) with Symfony Flex
- Automatic HTTPS (in dev and prod)
- HTTP/3 and [Early Hints](https://symfony.com/blog/new-in-symfony-6-3-early-hints) support
- Real-time messaging thanks to a built-in [Mercure hub](https://symfony.com/doc/current/mercure.html)
- [Vulcain](https://vulcain.rocks) support
- Native [XDebug](docs/symfony-docker/xdebug.md) integration
- [Hot Reloading](https://frankenphp.dev/docs/symfony-docker/hot-reload/)
- [Dev Container](https://containers.dev/) support
- [AI coding agents](docs/symfony-docker/agents.md) with an optional network sandbox
- Rootless, slim production image

**Enjoy!**

## Docs

1. [Options available](docs/symfony-docker/options.md)
2. [Using Symfony Docker with an existing project](docs/symfony-docker/existing-project.md)
3. [Support for extra services](docs/symfony-docker/extra-services.md)
4. [Deploying in production](docs/symfony-docker/production.md)
5. [Debugging with Xdebug](docs/symfony-docker/xdebug.md)
6. [TLS Certificates](docs/symfony-docker/tls.md)
7. [Using MySQL instead of PostgreSQL](docs/symfony-docker/mysql.md)
8. [Using Alpine Linux instead of Debian](docs/symfony-docker/alpine.md)
9. [Using a justfile](docs/symfony-docker/justfile.md)
10. [Updating the template](docs/symfony-docker/updating.md)
11. [Troubleshooting](docs/symfony-docker/troubleshooting.md)
12. [Using AI coding agents](docs/symfony-docker/agents.md)

## Login com Google

O backend autentica por **authorization code**: o front-end obtém o `code` no Google e o envia
para `POST /api/v1/auth/social/google`. O backend troca o código, valida o `id_token` pelo JWKS
do Google e emite o access token JWT + o refresh token opaco no cookie `HttpOnly`.

### 1. Criar as credenciais no Google Cloud

1. Acesse o [Google Cloud Console](https://console.cloud.google.com/) e selecione (ou crie) um projeto.
2. Em **APIs & Services → OAuth consent screen**, configure a tela de consentimento.
   - Em modo **Testing**, adicione em **Test users** todas as contas que farão login.
3. Em **APIs & Services → Credentials → Create credentials → OAuth client ID**:
   - Application type: **Web application**.
   - Em **Authorized redirect URIs**, cadastre **exatamente** as URLs de callback usadas pelo
     seu cliente (e, se for testar pelo Playground, também a URL dele):
     ```
     https://app.exemplo.com/auth/google/callback
     https://developers.google.com/oauthplayground
     ```
4. Copie o **Client ID** e o **Client secret**.

> O Google exige que o `redirect_uri` enviado na autorização e na troca do código seja idêntico
> ao cadastrado. Diferença de barra final, `http`/`https` ou porta gera `redirect_uri_mismatch`.

### 2. Configurar o backend

Coloque as credenciais em `.env.local` (não versionado), nunca em `.env`:

```env
GOOGLE_CLIENT_ID=seu-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=seu-client-secret

# Lista separada por vírgula. É uma allowlist de comparação exata:
# o redirectUri do request precisa estar aqui e ser o mesmo usado no Google.
GOOGLE_REDIRECT_URIS=https://app.exemplo.com/auth/google/callback,https://developers.google.com/oauthplayground
```

Ajuste também, conforme o ambiente:

```env
# Origem do front-end que pode chamar a API com cookies (tratada como regex).
CORS_ALLOW_ORIGINS='^https://app\.exemplo\.com$'

# Cookie do refresh token.
# SameSite=lax/strict quando front e API são same-site.
# SameSite=none (exige Secure) quando o front está em outro site, como em dev
# com front em http://localhost:3000 e API em https://api.sigame.localhost.
REFRESH_COOKIE_SECURE=1
REFRESH_COOKIE_SAMESITE=lax
```

Depois de alterar variáveis de ambiente, limpe o cache:

```bash
docker compose exec php php bin/console cache:clear
```

### 3. Fluxo no front-end

1. Redirecione o usuário para o Google com `response_type=code` e os escopos
   `openid email profile` (o `openid` é o que faz o Google devolver o `id_token`):

   ```
   https://accounts.google.com/o/oauth2/v2/auth
     ?client_id=SEU_CLIENT_ID
     &redirect_uri=https%3A%2F%2Fapp.exemplo.com%2Fauth%2Fgoogle%2Fcallback
     &response_type=code
     &scope=openid%20email%20profile
     &state=VALOR_ALEATORIO
   ```

   Gere e valide o `state` no cliente (proteção contra CSRF). PKCE é opcional; se usar,
   envie o `codeVerifier` no passo seguinte.

2. No callback, envie o `code` para o backend:

   ```bash
   curl -i -X POST 'https://api.sigame.localhost:8443/api/v1/auth/social/google' \
     -H 'Content-Type: application/json' \
     --data '{
       "code": "4/0Ab...",
       "redirectUri": "https://app.exemplo.com/auth/google/callback"
     }'
   ```

   Resposta (`200`):

   ```json
   {
     "data": {
       "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
       "tokenType": "Bearer",
       "expiresIn": 900
     },
     "meta": { "timestamp": "2026-11-04T12:00:00.000+00:00" }
   }
   ```

   O refresh token **não** aparece no corpo: vem em `Set-Cookie: refresh_token=rt_...`
   (`HttpOnly`, path `/api/v1/auth`). O front-end precisa chamar a API com credenciais
   habilitadas (`credentials: 'include'` no `fetch`, `withCredentials` no axios).

3. Use o access token em `Authorization: Bearer <accessToken>` e renove com
   `POST /api/v1/auth/refresh`, sem enviar corpo — o cookie é lido automaticamente.

### 4. Comportamento do backend

- O `id_token` é validado contra o JWKS do Google (`iss`, `aud`, expiração e assinatura).
- O login só é aceito com `email_verified=true`; caso contrário retorna
  `SOCIAL_EMAIL_NOT_VERIFIED`.
- Se o `sub` do Google já existe, o usuário é autenticado.
- Se não existe mas o e-mail verificado pertence a uma conta local, a identidade Google é
  **vinculada** a essa conta (sem duplicar usuário).
- Se não existe e-mail cadastrado, uma nova conta é criada (CPF nulo, onboarding pendente).
- Somente `google` está habilitado; outro provedor retorna `SOCIAL_PROVIDER_UNSUPPORTED`.
- O endpoint é limitado a 10 requisições por minuto por provedor + IP (`429` ao exceder).

## Usando o Google OAuth Playground

O [Google OAuth 2.0 Playground](https://developers.google.com/oauthplayground/) serve para
validar o Client ID/Secret, conferir escopos e inspecionar o `id_token` sem escrever um front-end.
Como o backend troca o `code` por conta própria, o Playground é mais útil para **diagnóstico** —
e também permite testar o endpoint, copiando o `code` antes de ele ser consumido.

### 1. Configurar o Playground com suas credenciais

1. Abra <https://developers.google.com/oauthplayground/>.
2. Clique na engrenagem (**OAuth 2.0 configuration**) no canto superior direito.
3. Marque **Use your own OAuth credentials** e informe o **Client ID** e o **Client secret**
   do mesmo cliente OAuth usado pelo backend.
4. Cadastre `https://developers.google.com/oauthplayground` em **Authorized redirect URIs**
   no Google Cloud Console (o Playground usa exatamente essa URL).
5. Feche a engrenagem.

> Se você não usar suas próprias credenciais, o código gerado pertence ao cliente do Google.
> O backend tentará trocá-lo com o seu Client ID/Secret e receberá `invalid_grant`.

### 2. Autorizar e obter o código

1. No **Step 1**, informe/adicione os escopos:
   ```
   openid
   email
   profile
   ```
   Use o campo **Input your own scopes** se eles não aparecerem na lista.
2. Clique em **Authorize APIs** e conclua o login com uma conta de teste.
3. Você volta para o Playground, no **Step 2**. **Não clique em “Exchange authorization code
   for tokens” ainda** — esse botão consome o código, que é de uso único.

### 3. Testar o endpoint do SIGAME

1. Copie o `code` da URL de callback exibida no navegador:
   ```
   https://developers.google.com/oauthplayground/?code=4%2F0Ab...&scope=openid%20email%20profile
   ```
2. Faça o URL-decode (o `%2F` vira `/`).
3. Envie para o backend, usando **exatamente** o `redirectUri` do Playground:

   ```bash
   curl -i -X POST 'https://api.sigame.localhost:8443/api/v1/auth/social/google' \
     -H 'Content-Type: application/json' \
     --data '{
       "code": "4/0Ab...",
       "redirectUri": "https://developers.google.com/oauthplayground"
     }'
   ```

   Esse `redirectUri` precisa estar em `GOOGLE_REDIRECT_URIS`; caso contrário o backend
   responde `SOCIAL_AUTHENTICATION_FAILED` antes mesmo de chamar o Google.

Se você demorar ou clicar em **Exchange** antes, o código já terá sido usado e o backend
retorna `SOCIAL_AUTHENTICATION_FAILED` (`invalid_grant`). Nesse caso, autorize novamente
para gerar outro código.

### 4. Inspecionar o id_token (sem o backend)

No **Step 2**, clique em **Exchange authorization code for tokens**. O Playground mostra
`access_token` e, como o escopo `openid` foi solicitado, também o `id_token`. Cole o `id_token`
em <https://jwt.io> (ou decodifique localmente) e confira as claims que o backend usa:

| Claim            | Uso no SIGAME                                             |
|------------------|-----------------------------------------------------------|
| `sub`            | identificador do usuário no Google (provider_subject)     |
| `email`          | e-mail da conta                                           |
| `email_verified` | precisa ser `true` para o login ser aceito                |
| `name`           | nome inicial do perfil, quando a conta é criada           |
| `picture`        | foto de perfil sugerida                                   |
| `iss` / `aud`    | validação do emissor e da audiência                       |

Isso permite confirmar o motivo de um `SOCIAL_EMAIL_NOT_VERIFIED` ou de um `aud` divergente
sem depender do fluxo completo do front-end.

### 5. Problemas comuns

| Sintoma                           | Causa provável                                                                                  |
|-----------------------------------|-------------------------------------------------------------------------------------------------|
| `redirect_uri_mismatch` no Google | URL não cadastrada no console ou diferente da usada na autorização                              |
| `invalid_grant` na troca          | Código já consumido, expirado, ou credenciais de outro cliente OAuth                            |
| `SOCIAL_AUTHENTICATION_FAILED`    | `redirectUri` fora de `GOOGLE_REDIRECT_URIS`, Client ID/Secret inválidos ou `id_token` inválido |
| `SOCIAL_EMAIL_NOT_VERIFIED`       | A conta Google não tem e-mail verificado ou o escopo `email` não foi solicitado                 |
| `SOCIAL_PROVIDER_UNSUPPORTED`     | Provider diferente de `google` na URL                                                           |
| Cookie não é enviado no refresh   | `SameSite`/`Secure` incompatíveis com a origem, ou requisição sem credenciais                   |
| Erro de CORS no navegador         | Origem ausente/incorreta em `CORS_ALLOW_ORIGINS`                                                |
