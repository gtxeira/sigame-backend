# SIGAME — Documentação da API (OpenAPI)

> **Última atualização:** 2026-11-08
>
> **Implementação:** `config/packages/nelmio_api_doc.php`, `src/Shared/Presentation/Http/OpenApi/` e atributos `OpenApi\Attributes` nos controllers.

Este documento registra as decisões de organização da documentação OpenAPI, renderizada pelo NelmioApiDocBundle e exibida pelo Scalar em `/doc`. O JSON fica em `/doc.json`.

## 1. Critérios de separação de tags

A divisão das tags segue quatro critérios, nesta ordem de prioridade:

1. **Recurso ou entidade de domínio (padrão REST).** Endpoints que manipulam o mesmo ciclo de vida de dados ficam juntos. Por isso `/users` e `/users/{id}` pertencem à mesma tag `Usuários` quando o CRUD administrativo existir.
2. **Ciclo de vida e intenção de uso.** Ações do próprio usuário são separadas da gestão administrativa. Quando o ciclo de vida tem semântica própria — senha, verificação de e-mail, identidade social — ele ganha uma tag própria. `Minha conta` fica reservada aos dados do perfil (`/users/me` e `/users/me/cpf`).
3. **Mecanismo de autenticação e segurança.** A emissão de credencial local fica em `Autenticação`; a rotação e a revogação dos tokens ficam em `Sessão`; o fluxo OAuth e o vínculo de identidades ficam em `Autenticação social`. Integrações com mecanismo próprio de autenticação (por exemplo, webhooks validados por assinatura HMAC) devem ter uma tag própria, como `Webhooks`.
4. **Funcionalidades transversais ou de sistema.** Endpoints de infraestrutura e monitoramento ficam em `Saúde` e não se misturam com a lógica de negócio.

Tags agrupam operações por intenção; elas **não** são uma fronteira de segurança. A exigência de autenticação é documentada por operação, com `#[Security]`.

## 2. Taxonomia atual

Os paths de negócio abaixo omitem o prefixo `/api/v1`; os probes usam `/api/health`.

| Tag | Rotas | Critério predominante | Justificativa |
|---|---|---|---|
| `Autenticação` | `/auth/register`, `/auth/login` | Mecanismo de autenticação | Entrada na conta com credencial local. |
| `Sessão` | `/auth/refresh`, `/auth/logout`, `/auth/logout/all` | Mecanismo de autenticação | Rotação e revogação dos tokens de uma sessão. |
| `Autenticação social` | `/auth/social/{provider}/start`, `/auth/social/{provider}/callback`, `/auth/social/{provider}/link`, `/users/me/identities/{provider}` | Mecanismo de autenticação | Ciclo OAuth e vínculo de identidades sociais. |
| `Verificação de e-mail` | `/auth/email/verify`, `/auth/email/verification/resend` | Ciclo de vida do recurso | Confirmação pública e reenvio autenticado do e-mail canônico. |
| `Senha` | `/auth/password/forgot`, `/auth/password/reset`, `/users/me/password` | Ciclo de vida do recurso | Recuperação pública, redefinição por token e troca autenticada. |
| `Minha conta` | `/users/me`, `/users/me/cpf` | Responsabilidade/intenção (self-service) | Dados do próprio usuário autenticado, sem `{id}` e sem privilégio administrativo. |
| `Onboarding` | `/users/me/onboarding` | Ciclo de vida do recurso | Conclusão única do onboarding inicial, com promoção ao nível 2. |
| `Saúde` | `/api/health/liveness`, `/api/health/readiness` | Funcionalidade transversal/sistema | Probes operacionais, sem lógica de negócio. |
| `Usuários` (planejado) | `/users`, `/users/{id}` | Recurso/entidade de domínio (REST) | Mesmo agregado e ciclo de vida: coleção e item. Gestão global/administrativa. |
| `Webhooks` (futuro) | `/webhooks/*` | Mecanismo de autenticação | Assinatura HMAC, não Bearer JWT; não deve se misturar com `Autenticação`. |

A tag `Usuários` ainda não aparece no documento gerado porque não existe endpoint administrativo de usuários. O swagger-php remove tags sem operações. Quando o CRUD administrativo for criado, adicionar a tag em `documentation.tags` e o grupo `Administração` em `documentation.x-tagGroups`.

## 3. Decisão sobre `/users`

- `/users` e `/users/{id}` compartilham a tag `Usuários`, pelo critério de recurso.
- `/users/me` e `/users/me/cpf` ficam em `Minha conta`, pelo critério de intenção self-service.
- As demais rotas `/users/me/*` ficam na tag do respectivo ciclo de vida: `POST /users/me/password` em `Senha`, `POST /users/me/identities/{provider}` em `Autenticação social` e `POST /users/me/onboarding` em `Onboarding`.
- A separação entre a coleção e o item não é feita por tag; as operações aparecem como entradas distintas dentro de `Usuários`.
- Se no futuro a coleção e o item tiverem públicos ou ciclos de vida diferentes (por exemplo, `/users` restrito a administradores e `/users/{id}` self-service), a separação passa a ser justificada.
- A rota `/users/me` precisa continuar sendo declarada antes de `/users/{id}` ou, preferencialmente, `{id}` deve ter um requirement que não aceite o literal `me`.

## 4. Grupos do Scalar (`x-tagGroups`)

Os grupos organizam as tags na navegação do Scalar. Os nomes dos grupos são intencionalmente diferentes dos nomes das tags para evitar cabeçalhos duplicados.

| Grupo | Tags |
|---|---|
| `Acesso` | `Autenticação`, `Sessão`, `Autenticação social`, `Verificação de e-mail`, `Senha` |
| `Conta` | `Minha conta`, `Onboarding` |
| `Sistema` | `Saúde` |
| `Administração` | `Usuários` (quando o CRUD administrativo existir) |
| `Integrações` | `Webhooks` (futuro) |

Toda tag documentada deve pertencer a exatamente um grupo, e todo grupo deve referenciar apenas tags existentes. O teste `ApiDocumentationTest` verifica essa consistência.

## 5. Regras para novos endpoints

1. Classifique primeiro pelo ciclo de vida do recurso.
2. Se a ação é entrada na conta com credencial local, use `Autenticação`; se gerencia a sessão, use `Sessão`; se é OAuth ou vínculo de identidade social, use `Autenticação social`.
3. Se a ação pertence a um ciclo com semântica própria, use a tag do ciclo (`Senha`, `Verificação de e-mail`, `Onboarding`). `Minha conta` fica reservada aos dados do perfil (`/users/me`, `/users/me/cpf`).
4. Se o mecanismo de autenticação é diferente do Bearer JWT, crie uma tag própria (por exemplo, `Webhooks`).
5. Se o endpoint é de infraestrutura, monitoramento ou diagnóstico, use `Saúde`.
6. Ao criar uma tag, adicione a descrição em `documentation.tags` e inclua-a em um grupo de `documentation.x-tagGroups`.
7. Ao escrever o summary, siga a convenção da seção [Convenção de escrita dos summaries](#6-convenção-de-escrita-dos-summaries).

## 6. Convenção de escrita dos summaries

Todo summary de operação segue a mesma forma: verbo no infinitivo, sentence case, sem ponto final, sem parênteses e com até 60 caracteres. O summary é um rótulo de navegação; a precisão técnica fica na descrição.

| Operação | Verbo |
|---|---|
| `GET` de coleção | `Listar` |
| `GET` de item | `Obter` |
| `POST` de criação de recurso | `Criar` |
| `POST` de criação de conta | `Cadastrar` |
| `PUT` | `Substituir` |
| `PATCH` | `Atualizar` ou verbo de domínio (`Definir`) |
| `DELETE` | `Remover` |
| Ação de domínio | verbo específico (`Autenticar`, `Renovar`, `Encerrar`, `Revogar`, `Verificar`, `Reenviar`, `Solicitar`, `Redefinir`, `Alterar`, `Iniciar`, `Concluir`, `Vincular`) |

Decisões de vocabulário:

- `Cadastrar` é reservado para criação de conta (`/auth/register`); as demais criações de recurso usam `Criar`.
- `Remover` é o padrão para `DELETE`; não usar `Excluir`.
- `Atualizar` cobre `PATCH` genérico; quando a operação tem semântica própria, use o verbo de domínio (`Definir o CPF`, `Renovar o par de tokens`).
- O summary não repete a tag: dentro de `Autenticação social`, `Iniciar o fluxo de login social` já é específico o bastante.

Termos técnicos mantidos em inglês: `access token`, `refresh token`, `authorization code`, `cookie HttpOnly` e `probe`. Acrônimos ficam em caixa alta: `CPF`, `JWT`, `HTTP` e `JWKS`.

O swagger-php usa a **primeira linha** do PHPDoc como `summary` e o restante, separado por uma linha em branco, como `description`. Ao editar o comentário, mantenha a primeira linha curta e sem pontuação final.

Summaries atuais, usados como referência:

| Endpoint | Summary |
|---|---|
| `POST /auth/register` | `Cadastrar usuário` |
| `POST /auth/login` | `Autenticar com e-mail e senha` |
| `POST /auth/social/{provider}/start` | `Iniciar o fluxo de login social` |
| `POST /auth/social/{provider}/callback` | `Concluir o login social` |
| `POST /auth/social/{provider}/link` | `Vincular conta social pelo ticket` |
| `POST /users/me/identities/{provider}` | `Vincular identidade social` |
| `POST /auth/email/verify` | `Verificar o e-mail` |
| `POST /auth/email/verification/resend` | `Reenviar verificação de e-mail` |
| `POST /auth/password/forgot` | `Solicitar redefinição de senha` |
| `POST /auth/password/reset` | `Redefinir a senha` |
| `POST /users/me/password` | `Alterar a senha` |
| `POST /auth/refresh` | `Renovar o par de tokens` |
| `POST /auth/logout` | `Encerrar a sessão atual` |
| `POST /auth/logout/all` | `Encerrar todas as sessões` |
| `GET /users/me` | `Obter os dados do usuário autenticado` |
| `PATCH /users/me/cpf` | `Definir o CPF do usuário autenticado` |
| `GET /api/health/liveness` | `Verificar a execução da aplicação` |
| `GET /api/health/readiness` | `Verificar a prontidão da aplicação` |

A convenção vale apenas para summaries de operação. Descrição de operação continua em frase completa no presente, com ponto final. Descrição de resposta (`Dados do usuário autenticado.`) e descrição de campo (`E-mail do usuário.`) são frases nominais, porque descrevem resultado ou conteúdo, não uma ação.

O teste `ApiDocumentationTest` verifica o formato do summary e a primeira palavra contra a lista de verbos aprovados.

## 7. Segurança na documentação

A segurança é declarada explicitamente por operação, com o atributo do Nelmio:

```php
use Nelmio\ApiDocBundle\Attribute\Security;

#[IsGranted('ROLE_USER')]
#[Security(name: 'bearerAuth')]
public function me(): JsonResponse
```

O esquema `bearerAuth` é declarado uma única vez em `documentation.components.securitySchemes`. Endpoints públicos não recebem `#[Security]`; a ausência da propriedade `security` já significa que não há exigência de autenticação.

O teste `ApiDocumentationTest` cruza o `#[IsGranted]` real do controller com o `security` documentado, para evitar drift entre código e documentação.

## 8. Versionamento

- Rotas de negócio: `/api/v1`.
- Health e probes operacionais: `/api/health`, sem versionamento.
- Documentação: `/doc` e `/doc.json`, sem versionamento.

O prefixo `/api/v1` é declarado na classe do controller; as rotas dos métodos são relativas. Novas versões devem adicionar controllers em `/api/v2` sem alterar o contrato de `/api/v1`.

## 9. Meta adicional em respostas

O envelope padrão sempre inclui `meta.timestamp`. Quando um endpoint precisa devolver campos adicionais em `meta`, o atributo da resposta recebe um `ApiMeta`:

```php
use App\Shared\Presentation\Http\OpenApi\ApiMeta;
use OpenApi\Attributes as OA;

#[ResourceConflictResponse(
    examples: [...],
    meta: new ApiMeta(
        required: ['link'],
        properties: [
            new OA\Property(
                property: 'link',
                type: 'object',
                required: ['token', 'expiresAt'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'a1b2c3...'),
                    new OA\Property(
                        property: 'expiresAt',
                        type: 'string',
                        format: 'date-time',
                        example: '2026-11-06T12:00:00.000+00:00',
                    ),
                ],
            ),
        ],
    ),
)]
```

O `ApiMeta` é composto com `ApiResponseMeta` via `allOf`, então o `timestamp` continua obrigatório e o schema não duplica a definição. O exemplo nomeado é derivado do schema; se o `value` do exemplo trouxer um `meta` explícito, o `FillNamedExampleValues` mescla os campos derivados sem sobrescrever os explícitos.

## 10. Relação com outros documentos

- Contrato de autenticação e exemplos: [autenticacao.md](autenticacao.md)
- Endpoints implementados e roadmap: [implementacao.md](implementacao.md)
- Convenções de arquitetura: [arquitetura.md](arquitetura.md)
