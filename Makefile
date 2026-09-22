# Executables (local)
DOCKER_COMP = docker compose --env-file .env --env-file .env.dev --env-file .env.local

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
SYMFONY  = $(PHP) bin/console

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh composer vendor sf cc test test-db-setup test-db-drop cs phpstan

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: ## Builds the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

start: build up ## Build and start the containers

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

sh: ## Connect to the FrankenPHP container
	@$(PHP_CONT) sh

bash: ## Connect to the FrankenPHP container via bash so up and down arrows go to previous commands
	@$(PHP_CONT) bash

test: ## Start tests with pest, pass the parameter "c=" to add options, example: make test c="--group e2e --stop-on-failure"
	@$(eval c ?=)
	@$(DOCKER_COMP) exec -e APP_ENV=test php vendor/bin/pest $(c)

test-db-setup: ## Create test database and run migrations
	@$(DOCKER_COMP) exec -e APP_ENV=test php php bin/console doctrine:database:create --if-not-exists --no-interaction
	@$(DOCKER_COMP) exec -e APP_ENV=test php php bin/console doctrine:migrations:migrate --no-interaction

test-db-drop: ## Drop test database
	@$(DOCKER_COMP) exec -e APP_ENV=test php php bin/console doctrine:database:drop --if-exists --force --no-interaction


## —— Code Quality 🧹 ———————————————————————————————————————————————————————
cs: ## Run php-cs-fixer, pass the parameter "c=" to add options, example: make cs c="--dry-run --diff"
	@$(eval c ?=)
	@$(DOCKER_COMP) exec php vendor/bin/php-cs-fixer fix $(c)

cs-check: ## Verifica padrões de código sem alterar os arquivos
	@$(DOCKER_COMP) exec php vendor/bin/php-cs-fixer fix --dry-run --diff

phpstan: ## Run phpstan static analysis
	@$(DOCKER_COMP) exec php bin/console cache:warmup --env=dev
	@$(DOCKER_COMP) exec php vendor/bin/phpstan analyse --no-progress

pest: ## Run pest (alias for test)
	@$(MAKE) test


## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction
vendor: composer

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Clear the cache
cc: sf
