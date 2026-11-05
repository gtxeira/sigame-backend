# Documentação da API (OpenAPI)

Este documento registra as decisões de organização da documentação OpenAPI da SIGAME,
renderizada pelo Nelmio API Doc Bundle e exibida pelo Scalar em `/doc`.

## Critérios de separação de tags

A divisão das tags segue quatro critérios, nesta ordem de prioridade:

1. **Recurso ou entidade de domínio (padrão REST).** Endpoints que manipulam o mesmo
   ciclo de vida de dados ficam juntos. Por isso `/users` e `/users/{id}` pertencem à
   mesma tag `Usuários`.
2. **Responsabilidade e intenção de uso.** Ações que o próprio usuário executa na sua
   conta são separadas da gestão administrativa. Por isso `/users/me` e
   `/users/me/cpf` ficam em `Minha conta`, e não em `Usuários`.
3. **Mecanismo de autenticação e segurança.** Rotas de sessão/credenciais ficam em
   `Autenticação`. Integrações com mecanismo próprio de autenticação (por exemplo,
   webhooks validados por assinatura HMAC) devem ter uma tag própria, como `Webhooks`.
4. **Funcionalidades transversais ou de sistema.** Endpoints de infraestrutura e
   monitoramento ficam em `Infraestrutura` e não se misturam com a lógica de negócio.

Tags agrupam operações por intenção; elas **não** são uma fronteira de segurança. A
exigência de autenticação é documentada por operação, com `#[Security]`.

## Taxonomia atual

Os paths de negócio abaixo omitem o prefixo `/api/v1`; os probes usam `/api/health`.

| Tag | Rotas | Critério predominante | Justificativa |
|---|---|---|---|
| `Autenticação` | `/auth/register`, `/auth/login`, `/auth/social/{provider}`, `/auth/refresh`, `/auth/logout`, `/auth/logout/all` | Mecanismo de autenticação/segurança | Ciclo de credenciais: emissão, rotação e revogação de tokens. Pode conter operações públicas e protegidas; a proteção é documentada por operação. |
| `Minha conta` | `/users/me`, `/users/me/cpf` | Responsabilidade/intenção (self-service) | Ações do próprio usuário autenticado, sem `{id}` e sem privilégio administrativo. |
| `Usuários` | `/users`, `/users/{id}` (planejado) | Recurso/entidade de domínio (REST) | Mesmo agregado e ciclo de vida: coleção e item. Gestão global/administrativa. |
| `Infraestrutura` | `/api/health/liveness`, `/api/health/readiness` | Funcionalidade transversal/sistema | Probes operacionais, sem lógica de negócio. |
| `Webhooks` (futuro) | `/webhooks/*` (futuro) | Mecanismo de autenticação | Assinatura HMAC, não Bearer JWT; não deve se misturar com `Autenticação`. |

A tag `Usuários` ainda não aparece no documento gerado porque não existe endpoint
administrativo de usuários. O swagger-php remove tags sem operações. Quando o CRUD
administrativo for criado, adicionar a tag em `documentation.tags` e o grupo
`Administração` em `documentation.x-tagGroups`.

## Decisão sobre `/users`

- `/users` e `/users/{id}` compartilham a tag `Usuários`, pelo critério de recurso.
- `/users/me` e `/users/me/cpf` ficam em `Minha conta`, pelo critério de intenção
  self-service.
- A separação entre a coleção e o item não é feita por tag; as operações aparecem
  como entradas distintas dentro de `Usuários`.
- Se no futuro a coleção e o item tiverem públicos ou ciclos de vida diferentes
  (por exemplo, `/users` restrito a administradores e `/users/{id}` self-service), a
  separação passa a ser justificada.
- A rota `/users/me` precisa continuar sendo declarada antes de `/users/{id}` ou,
  preferencialmente, `{id}` deve ter um requirement que não aceite o literal `me`.

## Grupos do Scalar (`x-tagGroups`)

Os grupos organizam as tags na navegação do Scalar. Os nomes dos grupos são
intencionalmente diferentes dos nomes das tags para evitar cabeçalhos duplicados.

| Grupo | Tags |
|---|---|
| `Acesso e conta` | `Autenticação`, `Minha conta` |
| `Administração` | `Usuários` (quando o CRUD administrativo existir) |
| `Sistema` | `Infraestrutura` |
| `Integrações` | `Webhooks` (futuro) |

Toda tag documentada deve pertencer a exatamente um grupo, e todo grupo deve
referenciar apenas tags existentes. O teste `ApiDocumentationTest` verifica essa
consistência.

## Regras para novos endpoints

1. Classifique primeiro pelo recurso/entidade de domínio.
2. Se a ação é do próprio usuário sobre a própria conta, use `Minha conta`.
3. Se o mecanismo de autenticação é diferente do Bearer JWT, crie uma tag própria
   (por exemplo, `Webhooks`).
4. Se o endpoint é de infraestrutura, monitoramento ou diagnóstico, use
   `Infraestrutura`.
5. Ao criar uma tag, adicione a descrição em `documentation.tags` e inclua-a em um
   grupo de `documentation.x-tagGroups`.

## Segurança na documentação

A segurança é declarada explicitamente por operação, com o atributo do Nelmio:

```php
use Nelmio\ApiDocBundle\Attribute\Security;

#[IsGranted('ROLE_USER')]
#[Security(name: 'bearerAuth')]
public function me(): JsonResponse
```

O esquema `bearerAuth` é declarado uma única vez em
`documentation.components.securitySchemes`. Endpoints públicos não recebem
`#[Security]`; a ausência da propriedade `security` já significa que não há exigência
de autenticação.

O teste `ApiDocumentationTest` cruza o `#[IsGranted]` real do controller com o
`security` documentado, para evitar drift entre código e documentação.

## Versionamento

- Rotas de negócio: `/api/v1`.
- Health e probes operacionais: `/api/health`, sem versionamento.
- Documentação: `/doc` e `/doc.json`, sem versionamento.

O prefixo `/api/v1` é declarado na classe do controller; as rotas dos métodos são
relativas. Novas versões devem adicionar controllers em `/api/v2` sem alterar o
contrato de `/api/v1`.
