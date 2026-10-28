set default-list
set positional-arguments

_compose  := "docker compose --env-file .env --env-file .env.dev --env-file .env.local"
_exec     := _compose + " exec"
_php      := _exec + " php"
_test_php := _exec + " -e APP_ENV=test php"

# ── Docker 🐳 ────────────────────────────────────────────────────────────────

# Constrói as imagens Docker
[group('Docker')]
build:
    @{{ _compose }} build --pull --no-cache

# Sobe os containers em modo detached (sem logs)
[group('Docker')]
up:
    @{{ _compose }} up --detach

# Constrói e sobe os containers
[group('Docker')]
start: build up

# Para os containers
[group('Docker')]
down:
    @{{ _compose }} down --remove-orphans

# Mostra logs em tempo real
[group('Docker')]
logs:
    @{{ _compose }} logs --tail=0 --follow

# Shell sh no container PHP
[group('Docker')]
sh:
    @{{ _exec }} php sh

# Shell bash no container PHP (setas do histórico funcionam)
[group('Docker')]
bash:
    @{{ _exec }} php bash

# ── Qualidade de código 🧹 ───────────────────────────────────────────────────

# Roda o php-cs-fixer --fix (altera os arquivos)
[group('Código')]
cs-fix *args:
    @{{ _php }} composer cs:fix -- {{ args }}

# Verifica o padrão de código sem alterar os arquivos
[group('Código')]
cs-check:
    @{{ _php }} composer cs:check

# Análise estática com phpstan
[group('Código')]
phpstan:
    @{{ _php }} composer phpstan

# ── Testes 🧪 ────────────────────────────────────────────────────────────────

# Roda os testes (pest); ex.: `just test --group e2e`
[group('Testes')]
test *args:
    @{{ _test_php }} composer test:db:setup
    @{{ _test_php }} vendor/bin/pest {{ args }}

# Roda apenas os testes unitários
[group('Testes')]
test-unit:
    @{{ _test_php }} composer test:unit

# Roda apenas os testes funcionais (HTTP)
[group('Testes')]
test-functional:
    @{{ _test_php }} composer test:functional

# Alias para `just test`
alias pest := test

# ── Composer 🧙 ──────────────────────────────────────────────────────────────

# Roda qualquer subcomando do composer; ex.: `just composer req symfony/orm-pack`
[group('Composer')]
composer *args:
    @{{ _php }} composer {{ args }}

# Instala as dependências do composer.lock (sem dev)
[group('Composer')]
vendor:
    @{{ _php }} composer install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction

# ── Symfony 🎵 ───────────────────────────────────────────────────────────────

# Roda um comando do console; ex.: `just sf about`
[group('Symfony')]
sf *args:
    @{{ _php }} php bin/console {{ args }}

# Limpa o cache
[group('Symfony')]
cc:
    @{{ _php }} php bin/console c:c
