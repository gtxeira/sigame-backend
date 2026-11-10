# SIGAME — Desenvolvimento

> **Última atualização:** 2026-11-09
>
> **Público:** pessoas que vão rodar, testar ou alterar o backend localmente.

Este guia cobre o ambiente local, os comandos do dia a dia, testes, qualidade de código e depuração. Para serviços, variáveis de ambiente e produção, veja [infraestrutura.md](infraestrutura.md).

## 1. Pré-requisitos

- [Docker Engine](https://docs.docker.com/engine/) com Docker Compose v2.10+.
- [just](https://just.systems/) — opcional, mas usado em todos os exemplos. O repositório fixa a versão em `mise.toml`; com [mise](https://mise.jdx.dev/) basta rodar `mise install`.
- Um editor com suporte a PHP. O projeto tem configuração de Dev Container em `.devcontainer/`.

Não é necessário instalar PHP, Composer ou PostgreSQL na máquina: tudo roda no container `php`.

## 2. Ambiente Docker

O `justfile` usa três arquivos de ambiente, nesta ordem:

```text
.env  →  .env.dev  →  .env.local
```

- `.env` — padrões versionados;
- `.env.dev` — padrões de desenvolvimento;
- `.env.local` — segredos e overrides locais, **não versionado**.

Como o `justfile` passa `--env-file .env.local` ao Docker Compose, crie o arquivo antes de rodar os comandos, mesmo que comece vazio:

```bash
touch .env.local
```

O Compose sobe:

| Serviço | Imagem | Uso |
|---|---|---|
| `php` | FrankenPHP + Caddy | Aplicação, HTTPS local e worker mode |
| `worker` | FrankenPHP + Caddy | Consome a fila `async` do Messenger |
| `postgres` | `postgres:18-alpine` | Banco de dados |
| `garage` | `dxflrs/garage` | S3 local para arquivos |

As definições de `postgres` e `garage` estão em `compose.override.yaml` (desenvolvimento). Os detalhes de portas, volumes e produção estão em [infraestrutura.md](infraestrutura.md).

## 3. Primeira execução

```bash
# Constrói as imagens
just build

# Sobe os containers em background
just up
```

Alternativa sem `just`:

```bash
docker compose --env-file .env --env-file .env.dev --env-file .env.local up --detach
```

Na primeira subida, o entrypoint do container:

1. instala as dependências do Composer, se `vendor/` estiver vazio;
2. espera o PostgreSQL ficar disponível;
3. aplica as migrations pendentes.

Depois de subir:

- API: <https://api.sigame.localhost:8443>
- Documentação interativa: <https://api.sigame.localhost:8443/doc>
- OpenAPI JSON: <https://api.sigame.localhost:8443/doc.json>
- Readiness: <https://api.sigame.localhost:8443/api/health/readiness>

O certificado TLS é autoassinado; aceite-o no navegador na primeira visita.

Para acompanhar logs:

```bash
just logs
```

### 3.1 Chave de encriptação e worker

O ambiente de desenvolvimento já traz uma chave em `.env.dev`. Para usar outra, gere com `just keygen` e defina em `.env.local`:

```dotenv
MESSENGER_MAIL_ENCRYPTION_KEY=<base64 de 32 bytes>
```

O worker do Messenger sobe junto com `just up`. Se ele estiver parado, os e-mails de verificação e reset ficam na fila `messenger_messages` até o worker voltar. Para rodar um consumidor manual use `just worker`; para acompanhar o serviço, `just worker-logs`.

O serviço `scheduler` consome `scheduler_activities` e `scheduler_gamification`. O primeiro executa o fechamento automático de atividades a cada 5 minutos; o segundo aplica a perda por inatividade uma vez por dia. Se ele estiver parado, atividades vencidas permanecem abertas e o deleveling atrasa até a próxima execução. Use `just scheduler-logs` e `just scheduler` para acompanhar ou rodar manualmente.

## 4. Comandos do dia a dia

### 4.1 Just

| Comando | O que faz |
|---|---|
| `just` | Lista as receitas disponíveis |
| `just build` | Constrói as imagens sem cache |
| `just up` | Sobe os containers em background |
| `just start` | `build` + `up` |
| `just down` | Para os containers e remove órfãos |
| `just logs` | Acompanha os logs |
| `just worker-logs` | Acompanha os logs do worker do Messenger |
| `just worker` | Executa o worker manualmente no container `php` |
| `just scheduler-logs` | Acompanha os logs do scheduler de atividades |
| `just scheduler` | Executa o consumidor do scheduler manualmente no container `php` |
| `just keygen` | Gera uma chave base64 de 32 bytes para `MESSENGER_MAIL_ENCRYPTION_KEY` |
| `just messenger-failed` | Lista as mensagens da fila de falhas |
| `just sh` / `just bash` | Abre shell no container `php` |
| `just sf <args>` | Roda `bin/console` no container |
| `just cc` | Limpa o cache do Symfony |
| `just composer <args>` | Roda o Composer no container |
| `just cs-fix [args]` | Aplica o php-cs-fixer |
| `just cs-check` | Verifica o padrão sem alterar arquivos |
| `just phpstan` | Roda a análise estática |
| `just test [args]` | Roda a suíte Pest completa |
| `just test-unit` | Roda apenas o grupo `unit` |
| `just test-functional` | Roda apenas o grupo `functional` |
| `just test-integration` | Roda apenas o grupo `integration` |

Exemplos:

```bash
just sf debug:router
just sf doctrine:migrations:status
just sf debug:messenger
just keygen
just test --filter="logs in"
```

### 4.2 Composer

| Script | O que faz |
|---|---|
| `composer cs:fix` | Aplica o php-cs-fixer |
| `composer cs:check` | Verifica o padrão em modo dry-run |
| `composer phpstan` | Aquece o cache e roda o PHPStan |
| `composer test` | Cria o banco de teste e roda a suíte completa |
| `composer test:unit` | Pest no grupo `unit` |
| `composer test:functional` | Cria o banco e roda o grupo `functional` |
| `composer test:integration` | Cria o banco e roda o grupo `integration` |
| `composer quality` | `cs:check` + `phpstan` + `test` |
| `composer test:db:setup` | Cria o banco de teste se não existir |
| `composer config-transformer` | Converte configuração YAML em PHP |

### 4.3 Comandos de manutenção da gamificação

| Comando | O que faz |
|---|---|
| `just sf app:gamification:audit-ledger` | Confere `xp_events` contra `user_progress` e `users.level`; não escreve nada |
| `just sf app:gamification:audit-ledger --user-id=<uuid>` | Audita apenas um usuário |
| `just sf app:gamification:audit-ledger --repair` | Alinha saldo, XP vitalício, nível materializado e projeção ao ledger |
| `just sf app:gamification:check-level-projection` | Compara apenas `users.level` com o saldo de XP |
| `just sf app:gamification:check-level-projection --repair` | Corrige `users.level` a partir do saldo |

O `--repair` do ledger não grava evento de XP: ele apenas corrige a materialização e publica a projeção de nível.
Se o ledger tiver saldo ou XP vitalício negativo, o reparo é recusado para investigação manual.

## 5. Testes

A suíte é dividida em três grupos, declarados em `tests/Pest.php`:

| Grupo | Base | O que cobre |
|---|---|---|
| `unit` | `Tests\TestCase` (PHPUnit puro) | Entidades, VOs, policies e handlers com ports mockadas |
| `functional` | `Tests\FunctionalTestCase` (`WebTestCase`) | Rotas, status, payload, serialização e autenticação |
| `integration` | `Tests\IntegrationTestCase` (`KernelTestCase`) | Repositórios Doctrine, tipos e adaptadores reais, sem HTTP |

### 5.1 Banco de teste

- O banco usa sufixo `_test` (configurado em `config/packages/doctrine.php`).
- `composer test:db:setup` cria o banco com `doctrine:database:create --if-not-exists`.
- O Foundry está configurado com `reset.mode: migrate`: o schema vem das migrations, não do mapping.
- O DAMA Doctrine Test Bundle abre uma transação por teste e faz rollback no final.
- Por isso, **não** use `TRUNCATE`, `CREATE DATABASE`, `commit()` ou `rollBack()` dentro de um teste.
- Dados criados em `beforeAll`/`setUpBeforeClass` não sobrevivem; use `beforeEach` ou o corpo do teste.

### 5.2 Como escrever testes

1. Use **Mockery** para as ports: `Mockery::mock(Interface::class)`, com `shouldReceive(...)->once()->with(...)` e `shouldNotReceive(...)`. Não use `createMock()`.
2. Evite repetir `->value()`: extraia o resultado para uma variável antes das asserções.
3. Use datasets do Pest para variações de **dados**, com chaves string:

   ```php
   it('rejects invalid cpf', function (string $input) {
       expect(Cpf::fromString($input)->isInvalid())->toBeTrue();
   })->with([
       'wrong length' => '1234567890',
       'all same digits' => '11111111111',
   ]);
   ```

4. Setup repetido de construção vira helper local (`makeUser()`), não dataset.
5. Teste funcional deve exercitar a rota pelo nome (`app_auth_login`), não por URL montada à mão.

### 5.3 Execução

```bash
just test              # tudo
just test-unit
just test-functional
just test-integration
just test --filter="rotates both tokens"
```

## 6. Qualidade de código

### 6.1 php-cs-fixer

O projeto usa o ruleset `@Symfony` com ajustes em `.php-cs-fixer.dist.php`.

```bash
just cs-fix
just cs-check
```

### 6.2 PHPStan

O nível é **6**, com extensões para Symfony, Pest, Mockery e PHPUnit. O cache fica em `var/cache/phpstan`.

```bash
just phpstan
```

### 6.3 CaptainHook

O `captainhook.json` valida mensagens de commit no padrão **Conventional Commits** e, no pre-commit, roda php-cs-fixer e PHPStan nos arquivos staged.

Tipos aceitos: `feat`, `fix`, `ci`, `docs`, `perf`, `refactor`, `style`, `test`, `chore`, `revert`.

Exemplos:

```text
feat(identity): adicionar onboarding
fix(auth): corrigir rotação de refresh token
docs: revisar documentação de arquitetura
```

## 7. Depuração com Xdebug

O `compose.override.yaml` habilita Xdebug no ambiente de desenvolvimento:

```yaml
XDEBUG_MODE: "${XDEBUG_MODE:-debug,develop}"
```

A configuração do PHP fica em `frankenphp/conf.d/20-app.dev.ini` e aponta para `host.docker.internal`. No VS Code/PhpStorm, configure um listener na porta `9003` e inicie a sessão de debug.

Para desligar temporariamente:

```bash
XDEBUG_MODE=off just up
```

## 8. Problemas comuns

| Sintoma | Causa provável | Ação |
|---|---|---|
| Navegador reclama do certificado | TLS autoassinado do Caddy | Aceite o certificado em `https://api.sigame.localhost:8443` |
| Porta 8000/8443 ocupada | Outro serviço na máquina | Ajuste `HTTP_PORT`/`HTTPS_PORT` em `.env.local` |
| `database is not reachable` | PostgreSQL ainda subindo | Aguarde o entrypoint ou veja `just logs` |
| Migration não aplicada | Container antigo ou banco divergente | `just sf doctrine:migrations:migrate --no-interaction` |
| Alteração não aparece | Cache do Symfony | `just cc` |
| Erro de permissão em `var/` | Uso de usuário diferente no host | `docker compose exec php chown -R nonroot:nonroot var` |
| Teste falha por banco sujo | Transação/Truncate manual | Remova operações explícitas de transação do teste |

Para detalhes do template Docker, consulte a documentação oficial:

- <https://github.com/dunglas/symfony-docker>
- <https://github.com/dunglas/symfony-docker/tree/main/docs>
- <https://frankenphp.dev/docs/>
- <https://symfony.com/doc/current/>
