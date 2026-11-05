# SIGAME — Autenticação e conta

> **Última atualização:** 2026-11-05
>
> **Implementação:** `src/Identity/Application/UseCase/`, `src/Identity/Infrastructure/Security/` e `src/Identity/Presentation/Http/Controller/`

Este documento descreve o contrato de autenticação da API: tokens, cookies, endpoints, códigos de erro, rate limiting e o fluxo de login com Google.

## 1. Modelo de tokens

O SIGAME **não usa "JWT duplo"**. Apenas o access token é JWT; o refresh token é uma credencial opaca de alta entropia.

| Token | Formato | Duração | Persistência | Onde trafega |
|---|---|---|---|---|
| Access token | JWT HS256 | 15 minutos | Nenhuma (stateless) | `Authorization: Bearer <token>` |
| Refresh token | `rt_` + 64 caracteres hexadecimais | 7 dias, renovados a cada rotação | SHA-256 no banco | Cookie `HttpOnly` |

### 1.1 Access token

Claims:

```json
{
  "iss": "sigame",
  "aud": "sigame-api",
  "sub": "uuid-do-usuario",
  "typ": "access",
  "jti": "uuid-do-token",
  "iat": 1760000000,
  "nbf": 1760000000,
  "exp": 1760000900
}
```

| Campo | Uso |
|---|---|
| `iss` | Emissor (`ACCESS_TOKEN_ISSUER`) |
| `aud` | Audiência (`ACCESS_TOKEN_AUDIENCE`) |
| `sub` | UUID do usuário (`UserId`) |
| `typ` | Precisa ser `access` |
| `jti` | Identificador único do token |
| `iat` / `nbf` / `exp` | Janela de validade |

O access token é validado a cada requisição. O usuário é recarregado do banco, então uma suspensão passa a valer imediatamente. Não existe lista de access tokens revogados; eles expiram em até 15 minutos.

### 1.2 Refresh token

- Valor bruto: `rt_` + `bin2hex(random_bytes(32))`.
- Banco: apenas SHA-256 do valor bruto.
- Cookie: `HttpOnly`, `Secure` configurável, `SameSite` configurável, path `/api/v1/auth`.
- Cada token pertence a uma **família** (`family_id`), que representa uma sessão/dispositivo.
- No máximo 5 famílias ativas por usuário.

### 1.3 Rotação e detecção de reuso

1. O refresh token atual é revogado e aponta para o substituto (`replaced_by_id`).
2. Um novo token é criado na mesma família.
3. Se um token revogado for reapresentado **fora da janela de graça**, toda a família é revogada.
4. Dentro da janela de graça, o endpoint retorna `REFRESH_TOKEN_ALREADY_ROTATED` sem revogar a família — isso evita falso positivo em requisições concorrentes legítimas.
5. A rotação é serializada por lock com chave derivada do hash do token.

A janela é configurada por `REFRESH_TOKEN_REUSE_GRACE_SECONDS` (padrão: 30 segundos).

### 1.4 Variáveis de configuração

| Variável | Padrão | Descrição |
|---|---|---|
| `ACCESS_TOKEN_SECRET` | — | Segredo HS256 com pelo menos 32 caracteres |
| `ACCESS_TOKEN_ISSUER` | `sigame` | Claim `iss` |
| `ACCESS_TOKEN_AUDIENCE` | `sigame-api` | Claim `aud` |
| `ACCESS_TOKEN_TTL` | `900` | Validade do access token em segundos |
| `REFRESH_TOKEN_TTL` | `604800` | Validade do refresh token em segundos |
| `REFRESH_TOKEN_REUSE_GRACE_SECONDS` | `30` | Janela de graça para concorrência |
| `REFRESH_TOKEN_MAX_FAMILIES` | `5` | Máximo de sessões ativas por usuário |
| `REFRESH_COOKIE_NAME` | `refresh_token` | Nome do cookie |
| `REFRESH_COOKIE_PATH` | `/api/v1/auth` | Path restrito do cookie |
| `REFRESH_COOKIE_SECURE` | `1` | Exige HTTPS |
| `REFRESH_COOKIE_SAMESITE` | `lax` | `lax`, `strict` ou `none` |

Em desenvolvimento com front em `http://localhost:3000` e API em outro host, use `REFRESH_COOKIE_SAMESITE=none` com `REFRESH_COOKIE_SECURE=1`. Em produção _same-site_, prefira `strict` ou `lax`.

---

## 2. Endpoints

Todas as rotas de negócio usam o prefixo `/api/v1`.

### 2.1 Cadastro

`POST /api/v1/auth/register` — público.

```json
{
  "cpf": "477.051.220-14",
  "email": "user@example.com",
  "name": "John Doe",
  "password": "secret123"
}
```

- `cpf` é opcional e pode vir formatado.
- A resposta `201` traz os dados do usuário.
- O cadastro **não autentica**: chame o login em seguida.
- Erros: `VALIDATION_ERROR`, `CPF_INVALID`, `EMAIL_INVALID`, `USER_CPF_ALREADY_EXISTS`, `EMAIL_ALREADY_EXISTS`.

### 2.2 Login local

`POST /api/v1/auth/login` — público.

```json
{
  "email": "user@example.com",
  "password": "secret123"
}
```

Resposta `200`:

```json
{
  "data": {
    "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "tokenType": "Bearer",
    "expiresIn": 900
  },
  "meta": { "timestamp": "2026-11-05T12:00:00.000+00:00" }
}
```

O refresh token **não** aparece no corpo. Ele vem em `Set-Cookie: refresh_token=rt_...`.

### 2.3 Login social

`POST /api/v1/auth/social/{provider}` — público. Apenas `provider = google`.

```json
{
  "code": "4/0Ab...",
  "redirectUri": "https://app.exemplo.com/auth/google/callback",
  "codeVerifier": null,
  "name": null
}
```

- `code` é o authorization code obtido no Google.
- `redirectUri` precisa estar exatamente na allowlist `GOOGLE_REDIRECT_URIS`.
- `codeVerifier` é opcional (PKCE).
- `name` é usado quando o provedor não devolve o nome (evolução para Apple).
- A resposta é o mesmo par de tokens do login local.
- Erros: `SOCIAL_PROVIDER_UNSUPPORTED`, `SOCIAL_AUTHENTICATION_FAILED`, `SOCIAL_EMAIL_NOT_VERIFIED`, `ACCOUNT_SUSPENDED`.

### 2.4 Renovação

`POST /api/v1/auth/refresh` — sem corpo; lê o cookie `refresh_token`.

Resposta `200` com novo access token e novo cookie. O token anterior é invalidado.

Erros possíveis:

| Código | HTTP | Quando |
|---|---|---|
| `REFRESH_TOKEN_INVALID` | 401 | Cookie ausente ou token desconhecido |
| `REFRESH_TOKEN_EXPIRED` | 401 | Token expirado |
| `REFRESH_TOKEN_REUSED` | 401 | Token revogado reapresentado fora da janela de graça; família revogada |
| `REFRESH_TOKEN_ALREADY_ROTATED` | 401 | Reapresentação dentro da janela de graça |
| `REFRESH_IN_PROGRESS` | 409 | Outra requisição está rotacionando o token |
| `ACCOUNT_SUSPENDED` | 403 | Conta suspensa |

Em qualquer falha, o cookie é limpo na resposta.

### 2.5 Logout

`POST /api/v1/auth/logout` — usa o cookie.

Revoga a família do refresh token apresentado e limpa o cookie. Retorna `204`. É idempotente para um cookie já revogado.

`POST /api/v1/auth/logout/all` — exige `Authorization: Bearer <accessToken>`.

Revoga todas as famílias do usuário autenticado. Retorna `204`.

### 2.6 Minha conta

`GET /api/v1/users/me` — exige Bearer JWT.

```json
{
  "data": {
    "id": "01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f",
    "cpf": "477.051.220-14",
    "email": "user@example.com",
    "name": "John Doe",
    "level": 1,
    "status": "ACTIVE",
    "onboardingStatus": "PENDING",
    "role": "USER",
    "createdAt": "2026-11-05T12:00:00.000+00:00"
  },
  "meta": { "timestamp": "2026-11-05T12:00:00.000+00:00" }
}
```

`PATCH /api/v1/users/me/cpf` — exige Bearer JWT.

```json
{ "cpf": "477.051.220-14" }
```

O CPF é write-once. Erros: `USER_CPF_ALREADY_SET`, `USER_CPF_ALREADY_EXISTS`, `CPF_INVALID`.

---

## 3. Rate limiting

| Limiter | Política | Limite | Chave |
|---|---|---|---|
| `auth_login` | sliding window | 5/min | e-mail + IP |
| `auth_social` | sliding window | 10/min | provider + IP |
| `auth_refresh` | sliding window | 30/min | IP |
| `user_cpf` | sliding window | 10/min | `user_id` |

O excesso retorna `429 TOO_MANY_REQUESTS` com `Retry-After` e headers `X-RateLimit-*`.

Os limites são configuráveis por `AUTH_LOGIN_RATE_LIMIT`, `AUTH_SOCIAL_RATE_LIMIT`, `AUTH_REFRESH_RATE_LIMIT` e `USER_CPF_RATE_LIMIT`.

> Com mais de uma instância da API em produção, o pool `cache.rate_limiter` precisa apontar para armazenamento compartilhado (Redis ou banco). Isso está registrado como pendência em [implementacao.md](implementacao.md).

---

## 4. Login com Google

O backend autentica por **authorization code**: o front-end obtém o `code` no Google e o envia para o backend. O backend troca o código, valida o `id_token` pelo JWKS do Google e emite o par de tokens.

### 4.1 Criar as credenciais no Google Cloud

1. Acesse o [Google Cloud Console](https://console.cloud.google.com/) e selecione (ou crie) um projeto.
2. Em **APIs & Services → OAuth consent screen**, configure a tela de consentimento.
   - Em modo **Testing**, adicione em **Test users** todas as contas que farão login.
3. Em **APIs & Services → Credentials → Create credentials → OAuth client ID**:
   - Application type: **Web application**.
   - Em **Authorized redirect URIs**, cadastre **exatamente** as URLs de callback usadas pelo cliente (e, se for testar pelo Playground, também a URL dele):

     ```
     https://app.exemplo.com/auth/google/callback
     https://developers.google.com/oauthplayground
     ```

4. Copie o **Client ID** e o **Client secret**.

> O Google exige que o `redirect_uri` enviado na autorização e na troca do código seja idêntico ao cadastrado. Diferença de barra final, `http`/`https` ou porta gera `redirect_uri_mismatch`.

### 4.2 Configurar o backend

Coloque as credenciais em `.env.local` (não versionado), nunca em `.env`:

```env
GOOGLE_CLIENT_ID=seu-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=seu-client-secret

# Lista separada por vírgula. É uma allowlist de comparação exata:
# o redirectUri do request precisa estar aqui e ser o mesmo usado no Google.
GOOGLE_REDIRECT_URIS=https://app.exemplo.com/auth/google/callback,https://developers.google.com/oauthplayground
```

Depois de alterar variáveis de ambiente, limpe o cache:

```bash
docker compose exec php php bin/console cache:clear
```

### 4.3 Fluxo no front-end

1. Redirecione o usuário para o Google com `response_type=code` e os escopos `openid email profile` (o `openid` faz o Google devolver o `id_token`):

   ```
   https://accounts.google.com/o/oauth2/v2/auth
     ?client_id=SEU_CLIENT_ID
     &redirect_uri=https%3A%2F%2Fapp.exemplo.com%2Fauth%2Fgoogle%2Fcallback
     &response_type=code
     &scope=openid%20email%20profile
     &state=VALOR_ALEATORIO
   ```

   Gere e valide o `state` no cliente (proteção contra CSRF). PKCE é opcional; se usar, envie o `codeVerifier` no passo seguinte.

2. No callback, envie o `code` para o backend:

   ```bash
   curl -i -X POST 'https://api.sigame.localhost:8443/api/v1/auth/social/google' \
     -H 'Content-Type: application/json' \
     --data '{
       "code": "4/0Ab...",
       "redirectUri": "https://app.exemplo.com/auth/google/callback"
     }'
   ```

3. Use o access token em `Authorization: Bearer <accessToken>` e renove com `POST /api/v1/auth/refresh`.

O front-end precisa chamar a API com credenciais habilitadas: `credentials: 'include'` no `fetch` ou `withCredentials` no axios.

### 4.4 Comportamento do backend

- O `id_token` é validado contra o JWKS do Google (`iss`, `aud`, expiração e assinatura).
- O login só é aceito com `email_verified=true`; caso contrário retorna `SOCIAL_EMAIL_NOT_VERIFIED`.
- Se o `sub` do Google já existe, o usuário é autenticado.
- Se não existe, mas o e-mail verificado pertence a uma conta local, a identidade Google é **vinculada** a essa conta (sem duplicar usuário).
- Se não existe e-mail cadastrado, uma nova conta é criada (CPF nulo, onboarding pendente).
- Somente `google` está habilitado; outro provedor retorna `SOCIAL_PROVIDER_UNSUPPORTED`.
- O endpoint é limitado a 10 requisições por minuto por provedor + IP (`429` ao exceder).

### 4.5 Usando o Google OAuth Playground

O [Google OAuth 2.0 Playground](https://developers.google.com/oauthplayground/) serve para validar o Client ID/Secret, conferir escopos e inspecionar o `id_token` sem escrever um front-end. Como o backend troca o `code` por conta própria, o Playground é mais útil para **diagnóstico** — e também permite testar o endpoint, copiando o `code` antes de ele ser consumido.

1. Abra <https://developers.google.com/oauthplayground/>.
2. Clique na engrenagem (**OAuth 2.0 configuration**) no canto superior direito.
3. Marque **Use your own OAuth credentials** e informe o **Client ID** e o **Client secret** do mesmo cliente OAuth usado pelo backend.
4. Cadastre `https://developers.google.com/oauthplayground` em **Authorized redirect URIs** no Google Cloud Console.
5. No **Step 1**, informe/adicione os escopos `openid`, `email` e `profile`.
6. Clique em **Authorize APIs** e conclua o login com uma conta de teste.
7. No **Step 2**, **não clique em "Exchange authorization code for tokens" ainda** — esse botão consome o código, que é de uso único.
8. Copie o `code` da URL de callback, faça o URL-decode e envie ao backend com **exatamente** o `redirectUri` do Playground:

   ```bash
   curl -i -X POST 'https://api.sigame.localhost:8443/api/v1/auth/social/google' \
     -H 'Content-Type: application/json' \
     --data '{
       "code": "4/0Ab...",
       "redirectUri": "https://developers.google.com/oauthplayground"
     }'
   ```

Se você demorar ou clicar em **Exchange** antes, o código já terá sido usado e o backend retorna `SOCIAL_AUTHENTICATION_FAILED` (`invalid_grant`). Nesse caso, autorize novamente.

Para inspecionar o `id_token` sem o backend, clique em **Exchange authorization code for tokens** no Step 2. O Playground mostra o `id_token`; cole-o em <https://jwt.io> e confira as claims usadas pelo SIGAME:

| Claim | Uso no SIGAME |
|---|---|
| `sub` | Identificador do usuário no Google (`provider_subject`) |
| `email` | E-mail da conta |
| `email_verified` | Precisa ser `true` para o login ser aceito |
| `name` | Nome inicial do perfil, quando a conta é criada |
| `picture` | Foto de perfil sugerida |
| `iss` / `aud` | Validação do emissor e da audiência |

### 4.6 Problemas comuns

| Sintoma | Causa provável |
|---|---|
| `redirect_uri_mismatch` no Google | URL não cadastrada no console ou diferente da usada na autorização |
| `invalid_grant` na troca | Código já consumido, expirado ou credenciais de outro cliente OAuth |
| `SOCIAL_AUTHENTICATION_FAILED` | `redirectUri` fora de `GOOGLE_REDIRECT_URIS`, Client ID/Secret inválidos ou `id_token` inválido |
| `SOCIAL_EMAIL_NOT_VERIFIED` | A conta Google não tem e-mail verificado ou o escopo `email` não foi solicitado |
| `SOCIAL_PROVIDER_UNSUPPORTED` | Provider diferente de `google` na URL |
| Cookie não é enviado no refresh | `SameSite`/`Secure` incompatíveis com a origem, ou requisição sem credenciais |
| Erro de CORS no navegador | Origem ausente/incorreta em `CORS_ALLOW_ORIGINS` |

---

## 5. Evolução para app nativo

O fluxo implementado é **authorization code**. Para o app nativo futuro, o SDK poderá enviar um `id_token`; o backend já possui validação OIDC por JWKS e o mesmo `SocialIdentityProviderInterface`. A evolução consiste em aceitar `id_token` além de `code` no request e adicionar o adapter do novo provedor.

Apple **não** está implementada nesta entrega. O enum `IdentityProvider` e o registry deixam a extensão pronta sem alterar o handler, o banco ou o controller.

---

## 6. Referências de código e testes

| Assunto | Arquivo |
|---|---|
| Emissão/rotação/revogação | `src/Identity/Application/Service/TokenPairIssuer.php` |
| Geração do access token | `src/Identity/Infrastructure/Security/LcobucciAccessTokenGenerator.php` |
| Geração do refresh opaco | `src/Identity/Infrastructure/Security/OpaqueRefreshTokenGenerator.php` |
| Autenticador Bearer | `src/Identity/Infrastructure/Security/AccessTokenAuthenticator.php` |
| Provider Google | `src/Identity/Infrastructure/Social/GoogleIdentityProvider.php` |
| Cookie de refresh | `src/Identity/Presentation/Http/Response/RefreshTokenCookieFactory.php` |
| Testes de rotação | `tests/Unit/Identity/Application/Service/TokenPairIssuerTest.php` |
| Testes HTTP de auth | `tests/Functional/Identity/Presentation/Http/AuthTest.php` |
| Testes de `/users/me` | `tests/Functional/Identity/Presentation/Http/MeTest.php` |
