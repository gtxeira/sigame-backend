# Justfile

This project uses [just](https://just.systems/) as its command runner instead of
the `Makefile` from the Symfony Docker template. It provides shortcuts for the most
common tasks: Docker, tests, code quality, Composer and Symfony commands.

To view all the available recipes with their descriptions, run `just` (or
`just --list`). Recipes are grouped by topic.

For example, in the [getting started section](/README.md#getting-started), the
`docker compose` commands can be replaced by:

1. Run `just build` to build fresh images
2. Run `just up` (detached mode without logs)
3. Run `just down` to stop the Docker containers

Arguments are passed straight through to the underlying command — no wrapper
syntax needed:

```console
$ just test --group e2e
$ just sf cache:clear
$ just composer req symfony/orm-pack
$ just cs-fix --dry-run
```

## Installation

The repository pins the tool in [`mise.toml`](/mise.toml), so if you use
[mise](https://mise.jdx.dev/) it is installed automatically:

```console
$ mise install
```

Alternatively, with any of:

```console
$ brew install just         # macOS
$ cargo install just        # any platform with a Rust toolchain
$ apt install just          # Debian/Ubuntu — check the version, see note below
```

> [!NOTE]
>
> The justfile uses `set default-list`, which requires **just >= 1.52**. Distro
> packages may be older than that (e.g. Ubuntu 24.04 ships 1.21); prefer `mise`,
> `brew` or the [standalone installer](https://github.com/casey/just?tab=readme-ov-file#installation)
> in that case.

## Running from within the container

The recipes run `docker compose` from the host. If you want to run `just` from
inside the `php` container instead, add the standalone binary to the
[Dockerfile](/Dockerfile) package list and rebuild the PHP image. There is no
`make`-style tab requirement: justfile recipes are indented with spaces and
`just --fmt` formats the file for you.

## The Template

<!-- markdownlint-disable MD013 -->

```just
# `just` (sem argumentos) lista as receitas com descrição.
# Uso: `just <receita> [argumentos...]` — ex.: `just test --group e2e`

set default-list # just >= 1.52: `just` → lista as receitas
set positional-arguments # "$@" preserva o quoting dos argumentos

compose := "docker compose --env-file .env --env-file .env.dev --env-file .env.local"

# ── Docker 🐳 ────────────────────────────────────────────────────────────────

# Constrói as imagens Docker
[group('Docker')]
build:
    @{{ compose }} build --pull --no-cache

# Sobe os containers em modo detached (sem logs)
[group('Docker')]
up:
    @{{ compose }} up --detach

# Constrói e sobe os containers
[group('Docker')]
start: build up

# Para os containers
[group('Docker')]
down:
    @{{ compose }} down --remove-orphans

# Mostra logs em tempo real
[group('Docker')]
logs:
    @{{ compose }} logs --tail=0 --follow

# Shell sh no container PHP
[group('Docker')]
sh:
    @{{ compose }} exec php sh

# Shell bash no container PHP (setas do histórico funcionam)
[group('Docker')]
bash:
    @{{ compose }} exec php bash

# ── Qualidade de código 🧹 ───────────────────────────────────────────────────

# Roda o php-cs-fixer; ex.: `just cs-fix --dry-run`
[group('Código')]
cs-fix *args:
    @{{ compose }} exec php composer cs:fix -- "$@"

# Verifica o padrão de código sem alterar os arquivos
[group('Código')]
cs-check:
    @{{ compose }} exec php composer cs:check

# Análise estática com phpstan
[group('Código')]
phpstan:
    @{{ compose }} exec php composer phpstan

# ── Testes 🧪 ────────────────────────────────────────────────────────────────

# Roda os testes (pest); ex.: `just test --group e2e`
[group('Testes')]
test *args:
    @{{ compose }} exec -e APP_ENV=test php composer test -- "$@"

# Roda apenas os testes unitários
[group('Testes')]
test-unit *args:
    @{{ compose }} exec -e APP_ENV=test php composer test:unit -- "$@"

# Cria o banco de testes e roda as migrations
[group('Testes')]
test-db-setup:
    @{{ compose }} exec -e APP_ENV=test php composer test:db:setup

# Remove o banco de testes
[group('Testes')]
test-db-drop:
    @{{ compose }} exec -e APP_ENV=test php composer test:db:drop

# Alias para `just test`
alias pest := test

# ── Composer 🧙 ──────────────────────────────────────────────────────────────

# Roda qualquer subcomando do composer; ex.: `just composer req symfony/orm-pack`
[group('Composer')]
composer *args:
    @{{ compose }} exec php composer "$@"

# Instala as dependências do composer.lock (sem dev)
[group('Composer')]
vendor:
    @{{ compose }} exec php composer install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction

# ── Symfony 🎵 ───────────────────────────────────────────────────────────────

# Roda um comando do console; ex.: `just sf about`
[group('Symfony')]
sf *args:
    @{{ compose }} exec php php bin/console "$@"

# Limpa o cache
[group('Symfony')]
cc:
    @{{ compose }} exec php php bin/console c:c
```

<!-- markdownlint-enable MD013 -->

## Adding and Modifying Recipes

- Put a `#` comment right above the recipe: it becomes the description shown in
  `just --list` and in shell completions.
- Use `[group('Name')]` above the recipe to file it under a section in `just --list`.
- Declare `*args` and use `"$@"` in the recipe body to forward arguments verbatim
  (quoting preserved).
- Run `just --fmt` after editing to normalize the file.

> [!NOTE]
>
> This justfile **replaces the Makefile of the upstream Symfony Docker template**.
> When [updating the template](/docs/symfony-docker/updating.md), keep this file
> and delete any `Makefile` the update reintroduces.
