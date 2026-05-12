COMPOSE=docker compose -f compose.yaml -f compose.override.yaml
PHP=$(COMPOSE) run --rm --no-deps php
NODE=$(COMPOSE) run --rm --no-deps node

.PHONY: all vendor build start stop assets watch cc db fixtures test help

all: build vendor assets start ## setup and start the project from scratch

.env: .env.dist
	cp .env.dist .env

compose.override.yaml: compose.override.yaml.dist
	cp compose.override.yaml.dist compose.override.yaml

build: .env compose.override.yaml ## build docker images
	$(COMPOSE) build

start: .env compose.override.yaml ## start the containers
	$(COMPOSE) up -d --remove-orphans

stop: compose.override.yaml ## stop the containers
	$(COMPOSE) down --remove-orphans

vendor: .env compose.override.yaml ## install php dependencies
	$(PHP) composer install
	$(PHP) bin/console lexik:jwt:generate-keypair --skip-if-exists

assets: .env compose.override.yaml ## build frontend assets
	$(NODE) npm install
	$(NODE) npm run build

watch: .env compose.override.yaml ## watch and rebuild assets on change
	$(NODE) npm run watch

cc: .env compose.override.yaml ## clear cache
	$(PHP) bin/console cache:clear

db: .env compose.override.yaml ## create, migrate and load fixtures
	$(PHP) bin/console doctrine:database:create --if-not-exists
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction
	$(PHP) bin/console hautelook:fixtures:load --no-interaction

test: .env compose.override.yaml ## run cs checks, phpstan and phpunit coverage
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(PHP) vendor/bin/phpstan analyse --memory-limit=256M
	$(PHP) bin/console doctrine:database:create --if-not-exists --env=test
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction --env=test
	$(PHP) bin/console hautelook:fixtures:load --no-interaction --env=test
	$(PHP) vendor/bin/phpunit --coverage-html var/coverage

help: ## show this help
	@grep -E '^[a-zA-Z_./-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
