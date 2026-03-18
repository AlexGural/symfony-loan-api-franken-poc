DOCKER_COMP         = docker compose
DOCKER_COMP_FRANKEN = docker compose -f docker-compose.franken.yml
PHP                 = $(DOCKER_COMP) exec php
PHP_FRANKEN         = $(DOCKER_COMP_FRANKEN) exec frankenphp
CONSOLE             = $(PHP) bin/console
CONSOLE_FRANKEN     = $(PHP_FRANKEN) php bin/console
COMPOSER            = $(PHP) composer
COMPOSER_FRANKEN    = $(PHP_FRANKEN) composer

.DEFAULT_GOAL := help

.PHONY: build up down destroy logs sh
build:     ## Build images (FPM)
	$(DOCKER_COMP) build
up:        ## Start stack (FPM)
	$(DOCKER_COMP) up -d
down:      ## Stop stack (FPM)
	$(DOCKER_COMP) down
destroy:   ## Stop containers & remove volumes
	$(DOCKER_COMP) down -v
logs:      ## Logs (usage: make logs s=php)
	$(DOCKER_COMP) logs -f $(s)
sh:        ## Shell into PHP (FPM)
	$(PHP) sh

.PHONY: build-franken up-franken down-franken destroy-franken sh-franken
build-franken:   ## Build images (FrankenPHP)
	$(DOCKER_COMP_FRANKEN) build
up-franken:      ## Start stack (FrankenPHP)
	$(DOCKER_COMP_FRANKEN) up -d
down-franken:    ## Stop stack (FrankenPHP)
	$(DOCKER_COMP_FRANKEN) down
destroy-franken: ## Stop FrankenPHP & remove volumes
	$(DOCKER_COMP_FRANKEN) down -v
logs-franken:    ## Logs (usage: make logs-franken s=frankenphp)
	$(DOCKER_COMP_FRANKEN) logs -f $(s)
sh-franken:      ## Shell into PHP (FrankenPHP)
	$(PHP_FRANKEN) sh

.PHONY: composer-install composer-require composer-update install require console cc
composer-install:  ## Composer install (FPM)
	$(COMPOSER) install
composer-require:  ## Composer require (FPM) (usage: make composer-require p=vendor/package)
	$(COMPOSER) require $(p)
composer-update:   ## Composer update (FPM) (usage: make composer-update [p=vendor/package] [o=-W])
	$(COMPOSER) update $(p) $(o)

# Backward-compatible aliases
install: composer-install ## Alias for composer-install
require: composer-require ## Alias for composer-require
console:  ## Symfony console (usage: make console c=...)
	$(CONSOLE) $(c)
cc:       ## Clear cache (FPM)
	$(CONSOLE) cache:clear

.PHONY: composer-install-franken composer-require-franken composer-update-franken install-franken require-franken console-franken cc-franken db-migrate-franken
composer-install-franken: ## Composer install (FrankenPHP)
	$(COMPOSER_FRANKEN) install
composer-require-franken: ## Composer require (FrankenPHP) (usage: make composer-require-franken p=vendor/package)
	$(COMPOSER_FRANKEN) require $(p)
composer-update-franken:  ## Composer update (FrankenPHP) (usage: make composer-update-franken [p=vendor/package] [o=-W])
	$(COMPOSER_FRANKEN) update $(p) $(o)

# Backward-compatible aliases
install-franken: composer-install-franken ## Alias for composer-install-franken
require-franken: composer-require-franken ## Alias for composer-require-franken
console-franken: ## Symfony console (usage: make console-franken c=...)
	$(CONSOLE_FRANKEN) $(c)
cc-franken:      ## Clear cache (FrankenPHP)
	$(CONSOLE_FRANKEN) cache:clear
db-migrate-franken: ## Run migrations (FrankenPHP)
	$(CONSOLE_FRANKEN) doctrine:migrations:migrate --no-interaction

.PHONY: db-create db-migrate db-fresh db-create-franken db-migrate-franken db-fresh-franken
db-create:   ## Create database (FPM)
	$(CONSOLE) doctrine:database:create --if-not-exists
db-migrate:  ## Run migrations (FPM)
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
db-fresh:    ## Drop, recreate & migrate (FPM)
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
db-create-franken: ## Create database (FrankenPHP)
	$(CONSOLE_FRANKEN) doctrine:database:create --if-not-exists
db-fresh-franken:  ## Drop, recreate & migrate (FrankenPHP)
	$(CONSOLE_FRANKEN) doctrine:database:drop --force --if-exists
	$(CONSOLE_FRANKEN) doctrine:database:create
	$(CONSOLE_FRANKEN) doctrine:migrations:migrate --no-interaction

.PHONY: cs cs-check psalm cs-franken cs-check-franken psalm-franken
cs:        ## Fix code style (FPM)
	$(PHP) ./vendor/bin/php-cs-fixer fix
cs-check:  ## Check code style (FPM)
	$(PHP) ./vendor/bin/php-cs-fixer fix --dry-run --diff
psalm:     ## Psalm (FPM)
	$(PHP) ./vendor/bin/psalm
cs-franken:      ## Fix code style (FrankenPHP)
	$(PHP_FRANKEN) ./vendor/bin/php-cs-fixer fix
cs-check-franken: ## Check code style (FrankenPHP)
	$(PHP_FRANKEN) ./vendor/bin/php-cs-fixer fix --dry-run --diff
psalm-franken:   ## Psalm (FrankenPHP)
	$(PHP_FRANKEN) ./vendor/bin/psalm

.PHONY: test test-franken test-unit test-unit-franken test-functional test-functional-franken test-db test-db-franken
test:             ## Tests (FPM)
	$(PHP) ./vendor/bin/phpunit
test-franken:     ## Tests (FrankenPHP)
	$(PHP_FRANKEN) ./vendor/bin/phpunit
test-unit:        ## Unit tests (FPM)
	$(PHP) ./vendor/bin/phpunit --testsuite=Unit
test-unit-franken: ## Unit tests (FrankenPHP)
	$(PHP_FRANKEN) ./vendor/bin/phpunit --testsuite=Unit
test-functional:  test-db ## Functional tests (creates test DB)
	$(PHP) ./vendor/bin/phpunit --testsuite=Functional
test-functional-franken: test-db-franken ## Functional tests (FrankenPHP)
	$(PHP_FRANKEN) ./vendor/bin/phpunit --testsuite=Functional
test-db:          ## Create test database (FPM)
	$(CONSOLE) --env=test doctrine:database:create --if-not-exists
test-db-franken:  ## Create test database (FrankenPHP)
	$(CONSOLE_FRANKEN) --env=test doctrine:database:create --if-not-exists

.PHONY: init init-franken
init:         ## Full setup (FPM)
init:         build up composer-install db-create db-migrate
init-franken: ## Full setup (FrankenPHP)
init-franken: build-franken up-franken composer-install-franken db-create-franken db-migrate-franken

.PHONY: bench bench-compare
bench:         ## Run k6 (usage: make bench m=fpm|franken)
	./bench/compare.sh $(m)
bench-compare: ## Compare FPM vs FrankenPHP
	./bench/compare.sh compare

.PHONY: help
help: ## Show this help
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' Makefile \
		| awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-20s\033[0m %s\n", $$1, $$2}' \
		| sed -e 's/^## /\n/'
