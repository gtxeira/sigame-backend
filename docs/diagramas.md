# Diagramas e modelo de dados

O diagrama de entidade-relacionamento (DER) do SIGAME **não é desenhado à mão**. Ele é gerado a
partir do PostgreSQL real — o mesmo schema criado pelas migrations — com o
[tbls](https://github.com/k1LoW/tbls). Assim, o diagrama não tem como divergir do banco: se uma
migration altera uma tabela, o próximo `just docs-erd` reflete a mudança.

## O que é gerado

Tudo em [`docs/db/`](db/README.md) é gerado e não deve ser editado manualmente.

| Artefato                                                          | Conteúdo                                                     |
|-------------------------------------------------------------------|--------------------------------------------------------------|
| [`db/README.md`](db/README.md)                                    | Visão geral: viewpoints, tabelas e DER completo (Mermaid)     |
| [`db/viewpoint-identity.md`](db/viewpoint-identity.md)            | Contas, identidades, modalidades e credenciais temporárias    |
| [`db/viewpoint-groups.md`](db/viewpoint-groups.md)                | Grupos, participação e transferência de responsabilidade      |
| [`db/viewpoint-activities.md`](db/viewpoint-activities.md)        | Atividades, inscrições, presença e remarcações                |
| [`db/viewpoint-gamification.md`](db/viewpoint-gamification.md)    | Progresso, ledger de XP e conquistas                          |
| [`db/viewpoint-notifications.md`](db/viewpoint-notifications.md)  | Inbox canônico e entregas por canal                           |
| [`db/viewpoint-administration.md`](db/viewpoint-administration.md)| Auditoria administrativa e suspensões                         |
| `db/viewpoint-*.mmd` e `db/schema.mmd`                            | Código Mermaid puro, para exportar em qualquer renderizador   |

Os DERs por contexto existem porque o schema inteiro em um único diagrama vira uma teia. Em cada
viewpoint aparecem apenas as tabelas do contexto mais `users`, que é o nó compartilhado. Para
manter a leitura, o diagrama mostra somente chaves primárias e estrangeiras; o detalhamento de
todas as colunas fica nas páginas `db/public.<tabela>.md`.

## Como regenerar

Os containers precisam estar no ar (`just up`), porque a geração lê o banco de desenvolvimento já
migrado.

```bash
just docs-erd
```

O recipe roda `tbls doc --rm-dist` e gera os `.mmd` por viewpoint. Nada é editado à mão.

Para verificar se a documentação commitada está atualizada — sem alterar arquivos — use:

```bash
just docs-erd-check
```

O comando sai com código diferente de zero quando o banco tem uma alteração que ainda não foi
regenerada. Ele é a base do gate de documentação no CI.

## Onde ajustar o conteúdo

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

## Exportar para o TCC/relatório

Os arquivos `.mmd` são Mermaid puro e podem ser exportados em PNG/SVG sem alterar a fonte:

- [mermaid.live](https://mermaid.live): cole o conteúdo do `.mmd` e exporte PNG/SVG;
- plugin Mermaid da IDE;
- `npx @mermaid-js/mermaid-cli -i docs/db/viewpoint-activities.mmd -o atividades.svg`.

O repositório não versiona as imagens exportadas para não duplicar a fonte de verdade. Se o
relatório precisar de uma imagem fixa, gere a partir do `.mmd` e guarde fora de `docs/db/`.
