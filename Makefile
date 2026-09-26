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


## —— Code Quality 🧹 ———————————————————————————————————————————————————————
cs-fix: ## Run php-cs-fixer, pass the parameter "c=" to add options, example: make cs c="--dry-run"
	@$(eval c ?=)
	@$(COMPOSER) cs:fix -- $(c)

cs-check: ## Verifica padrões de código sem alterar os arquivos
	@$(COMPOSER) cs:check

phpstan: ## Run phpstan static analysis
	@$(COMPOSER) phpstan

pest: ## Run pest (alias for test)
	@$(MAKE) test


## —— Tests 🧪 ————————————————————————————————————————————————————————————————
test: ## Start tests with pest, pass the parameter "c=" to add options, example: make test c="--group e2e"
	@$(eval c ?=)
	@$(DOCKER_COMP) exec -e APP_ENV=test php composer test -- $(c)

test-unit:
	@$(eval c ?=)
	@$(DOCKER_COMP) exec -e APP_ENV=test php composer test:unit -- $(c)

test-db-setup: ## Create test database and run migrations
	@$(DOCKER_COMP) exec -e APP_ENV=test php composer test:db:setup

test-db-drop: ## Drop test database
	@$(DOCKER_COMP) exec -e APP_ENV=test php composer test:db:drop


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
