# Short commands for the Docker setup. Run `make` to see them.
DC = docker compose
EXEC = $(DC) exec app

.DEFAULT_GOAL := help

help: ## Show this help
	@grep -E '^[a-z-]+:.*##' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Start the containers (site on http://localhost:8080)
	$(DC) up -d --build

down: ## Stop the containers
	$(DC) down

sh: ## Open a shell inside the PHP container
	$(EXEC) bash

install: ## Install PHP packages, JS vendors and build CSS
	$(EXEC) composer install
	$(EXEC) bin/console importmap:install
	$(EXEC) bin/console tailwind:build

console: ## Run a Symfony command: make console c="cache:clear"
	$(EXEC) bin/console $(c)

composer: ## Run a Composer command: make composer c="require foo/bar"
	$(EXEC) composer $(c)

css: ## Rebuild Tailwind CSS on every change (Ctrl+C to stop)
	$(EXEC) bin/console tailwind:build --watch

db: ## Run migrations and reload example data (admin accounts are kept)
	$(EXEC) bin/console doctrine:migrations:migrate --no-interaction
	$(EXEC) bin/console doctrine:fixtures:load --no-interaction --purge-exclusions=users

test: ## Run the PHPUnit tests
	$(EXEC) bin/phpunit

.PHONY: help up down sh install console composer css db test
