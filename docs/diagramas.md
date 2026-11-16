# Diagramas e modelo de dados

Nenhum diagrama do SIGAME é desenhado à mão. O DER e os casos de uso são gerados a partir de fontes
versionadas, e os checks falham quando a fonte muda e o artefato não é regenerado.

| Diagrama | Fonte de verdade | Gerador | Artefatos |
| -------- | ---------------- | ------- | --------- |
| Entidade-relacionamento | PostgreSQL criado pelas migrations | [tbls](https://github.com/k1LoW/tbls) | `docs/db/` |
| Casos de uso | [`docs/requisitos/casos-de-uso.yaml`](requisitos/casos-de-uso.yaml) + requisitos + rotas | comando `app:docs:usecases` + PlantUML | `docs/requisitos/casos-de-uso.md` e `docs/requisitos/casos-de-uso/` |

## Diagrama ER

O DER lê o banco de desenvolvimento já migrado. Se uma migration altera uma tabela, o próximo
`just docs-erd` reflete a mudança.

Tudo em [`docs/db/`](db/README.md) é gerado e não deve ser editado manualmente.

| Artefato                                                          | Conteúdo                                                     |
|-------------------------------------------------------------------|--------------------------------------------------------------|
| [`db/README.md`](db/README.md)                                    | Visão geral: contextos, tabelas e DER completo (Mermaid)      |
| [`db/viewpoint-identity.md`](db/viewpoint-identity.md)            | Contas, identidades, modalidades e credenciais temporárias    |
| [`db/viewpoint-groups.md`](db/viewpoint-groups.md)                | Grupos, participação e transferência de responsabilidade      |
| [`db/viewpoint-activities.md`](db/viewpoint-activities.md)        | Atividades, inscrições, presença e remarcações                |
| [`db/viewpoint-gamification.md`](db/viewpoint-gamification.md)    | Progresso, ledger de XP e conquistas                          |
| [`db/viewpoint-notifications.md`](db/viewpoint-notifications.md)  | Inbox canônico e entregas por canal                           |
| [`db/viewpoint-administration.md`](db/viewpoint-administration.md)| Auditoria administrativa e suspensões                         |
| `db/viewpoint-*.mmd` e `db/schema.mmd`                            | Mermaid puro, para exportar em qualquer renderizador          |

Os DERs por contexto existem porque o schema inteiro em um único diagrama vira uma teia. Em cada
contexto aparecem apenas as tabelas do módulo mais `users`, o nó compartilhado. Para manter a
leitura, o diagrama mostra somente chaves primárias e estrangeiras; o detalhamento de todas as
colunas fica nas páginas `db/public.<tabela>.md`.

```bash
just docs-erd        # regenera a documentação e os DERs
just docs-erd-check  # falha se o banco tiver mudança não regenerada
```

O que não vem do banco fica em [`.tbls.yml`](../.tbls.yml):

- **`comments`** — descrição de cada tabela exibida no índice e no DER. Comentários de coluna
  podem ser adicionados no mesmo bloco (`columnComments`).
- **`relations`** — relações de domínio que não existem como FK no banco (por exemplo `created_by`,
  `owner_id` e `actor_id`). O campo `def` vira o rótulo da aresta no diagrama.
- **`detectVirtualRelations`** — deriva relações por convenção de nome (`activities.group_id` →
  `groups.id`, `user_progress.user_id` → `users.id`).
- **`viewpoints`** — recortes por bounded context. Uma tabela nova aparece sozinha no DER completo;
  para incluí-la em um contexto, adicione-a ao viewpoint correspondente.

Tabelas de infraestrutura (`doctrine_migration_versions` e `messenger_messages`) ficam fora do
modelo em `exclude`.

## Casos de uso

O catálogo fica em [`docs/requisitos/casos-de-uso.yaml`](requisitos/casos-de-uso.yaml) e é validado
contra três fontes que já existem no repositório:

1. os requisitos funcionais (`### RFxx`) do [levantamento](requisitos/levantamento-de-requisitos.md);
2. a tabela de atores da seção 3.1 do mesmo documento;
3. as rotas `/api/v1` registradas no Router.

O comando falha se um RF ficar sem caso de uso, se um ator divergir, se uma rota pública ficar sem
caso de uso, se um caso implementado não tiver rota (ou um caso de roadmap tiver) ou se
`inclui`/`estende` apontarem para casos inexistentes, de outro diagrama ou formarem ciclo.

Artefatos gerados (não edite):

| Artefato | Conteúdo |
| -------- | -------- |
| [`requisitos/casos-de-uso.md`](requisitos/casos-de-uso.md) | Índice, cobertura e tabela de rastreabilidade |
| `requisitos/casos-de-uso/*.puml` | Fonte PlantUML de cada diagrama |
| `requisitos/casos-de-uso/*.svg` | Imagens versionadas, usadas no Markdown e no TCC |

São 15 diagramas: uma visão geral com um caso de uso por RF (associado ao ator principal) e um
diagrama por contexto, com todos os atores e permissões.

```bash
just docs-usecases        # valida, gera o Markdown e renderiza os SVGs
just docs-usecases-check  # valida e compara os artefatos, sem escrever
```

Cada caso de uso declara `id`, `nome`, `rf`, `modulo`, `diagrama`, `atores`, `objetivo`, `status`
(`implementado`, `roadmap` ou `adiado`), `api` e `rotas`. Casos sem endpoint usam `api: false` e
`motivo_sem_api` (por exemplo, rotinas do scheduler). Casos de roadmap/adiados aparecem em cinza ou
vermelho claro no diagrama, com o estereótipo `«roadmap»`/`«adiado»`.

## Exportar para o TCC/relatório

- **Casos de uso:** os SVGs já estão em `docs/requisitos/casos-de-uso/`. Para PNG em alta resolução:
  `docker compose run --rm plantuml -tpng docs/requisitos/casos-de-uso/*.puml`.
- **ER:** os `.mmd` são Mermaid puro e podem ser exportados no [mermaid.live](https://mermaid.live),
  pelo plugin da IDE ou por `npx @mermaid-js/mermaid-cli -i docs/db/viewpoint-activities.mmd -o atividades.svg`.

As imagens exportadas não são versionadas para não duplicar a fonte de verdade; gere a partir do
`.mmd`/`.puml` quando precisar.
