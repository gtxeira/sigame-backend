set default-list
set positional-arguments

_compose  := "docker compose --env-file .env --env-file .env.dev --env-file .env.local"
_exec     := _compose + " exec"
_php      := _exec + " php"
_test_php := _exec + " -e APP_ENV=test -e XDEBUG_MODE=off php"
_storage_bucket := "profile-images"
_uid      := `id -u`
_gid      := `id -g`
_docs     := _compose + " run --rm -T --user " + _uid + ":" + _gid + " docs"
_plantuml := _compose + " run --rm -T --user " + _uid + ":" + _gid + " plantuml"

# ── Docker 🐳 ────────────────────────────────────────────────────────────────

# Constrói as imagens Docker
[group('Docker')]
build:
    @{{ _compose }} build --pull --no-cache

# Sobe os containers em modo detached (sem logs) e habilita a leitura pública das fotos no Garage
[group('Docker')]
up:
    @{{ _compose }} up --detach
    @i=0; until {{ _compose }} exec -T garage /garage bucket website --allow {{ _storage_bucket }} >/dev/null 2>&1; do i=$((i+1)); if [ $i -ge 30 ]; then echo "Não foi possível habilitar a leitura pública de {{ _storage_bucket }}; rode just storage-setup." >&2; exit 1; fi; sleep 1; done

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

# Gera uma chave base64 de 32 bytes para MESSENGER_MAIL_ENCRYPTION_KEY
[group('Docker')]
keygen:
    @{{ _php }} php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'

# Mostra os logs do worker do Messenger
[group('Docker')]
worker-logs:
    @{{ _compose }} logs --tail=0 --follow worker

# Executa o worker do Messenger manualmente no container PHP
[group('Docker')]
worker:
    @{{ _php }} php bin/console messenger:consume async --time-limit=3600 --memory-limit=128M -vv

# Mostra os logs dos schedulers de atividades e gamificação
[group('Docker')]
scheduler-logs:
    @{{ _compose }} logs --tail=0 --follow scheduler

# Executa o consumidor dos schedulers manualmente no container PHP
[group('Docker')]
scheduler:
    @{{ _php }} php bin/console messenger:consume scheduler_activities scheduler_gamification --time-limit=3600 --memory-limit=128M -vv

# Lista as mensagens que falharam definitivamente
[group('Docker')]
messenger-failed:
    @{{ _php }} php bin/console messenger:failed:show

# Habilita leitura pública (website) do bucket de fotos no Garage local
[group('Docker')]
storage-setup:
    @{{ _exec }} garage /garage bucket website --allow {{ _storage_bucket }}
    @{{ _exec }} garage /garage bucket info {{ _storage_bucket }}

# Shell sh no container PHP
[group('Docker')]
sh:
    @{{ _exec }} php sh

# Shell bash no container PHP (setas do histórico funcionam)
[group('Docker')]
bash:
    @{{ _exec }} php bash

# ── Documentação 📚 ─────────────────────────────────────────────────────────

# Gera a documentação do banco e os DERs (tbls) a partir do PostgreSQL migrado
[group('Documentação')]
docs-erd:
    @{{ _docs }} doc --rm-dist
    @{{ _docs }} out -t mermaid -o docs/db/schema.mmd
    @for ctx in identity groups activities gamification notifications administration; do \
        {{ _docs }} out -t mermaid --viewpoint "$ctx" -o "docs/db/viewpoint-$ctx.mmd"; \
    done

# Verifica se a documentação do banco está atualizada (exit != 0 em caso de drift)
[group('Documentação')]
docs-erd-check:
    @{{ _docs }} diff

# Valida os casos de uso, gera o Markdown e renderiza os diagramas PlantUML
[group('Documentação')]
docs-usecases:
    @{{ _php }} php bin/console app:docs:usecases
    @{{ _php }} chown -R {{ _uid }}:{{ _gid }} docs/requisitos/casos-de-uso docs/requisitos/casos-de-uso.md
    @{{ _plantuml }} -failfast2 -tsvg docs/requisitos/casos-de-uso/*.puml

# Valida o catálogo de casos de uso e os artefatos, sem escrever
[group('Documentação')]
docs-usecases-check:
    @{{ _php }} php bin/console app:docs:usecases --check

# Gera toda a documentação (DER + casos de uso)
[group('Documentação')]
docs: docs-erd docs-usecases

# Valida toda a documentação sem escrever (DER + casos de uso)
[group('Documentação')]
docs-check: docs-erd-check docs-usecases-check

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

# Roda apenas os testes de integração (adaptadores reais, sem HTTP)
[group('Testes')]
test-integration:
    @{{ _test_php }} composer test:integration

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
