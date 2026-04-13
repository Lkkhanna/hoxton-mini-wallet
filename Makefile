.PHONY: up down build rebuild logs migrate seed test fresh help

help: ## Show available commands
	@echo ""
	@echo "Hoxton Mini Wallet"
	@echo "------------------------------"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'
	@echo ""

up: ## Start all containers
	docker compose up -d

down: ## Stop all containers
	docker compose down

build: ## Build all containers
	docker compose build

rebuild: ## Force rebuild all containers (no cache)
	docker compose build --no-cache

logs: ## View all container logs
	docker compose logs -f

logs-backend: ## View backend logs
	docker compose logs -f backend

logs-frontend: ## View frontend logs
	docker compose logs -f frontend

logs-db: ## View database logs
	docker compose logs -f db

migrate: ## Run database migrations
	docker compose exec backend php artisan migrate --force

seed: ## Run database seeders
	docker compose exec backend php artisan db:seed --force

fresh: ## Fresh migrate + seed (drops all tables)
	docker compose exec backend php artisan migrate:fresh --seed --force

test: ## Run backend tests
	docker compose exec backend php artisan test tests/Feature

tinker: ## Open Laravel tinker
	docker compose exec backend php artisan tinker

shell-backend: ## Open shell in backend container
	docker compose exec backend bash

shell-db: ## Open MySQL CLI
	docker compose exec db mysql -uwallet_user -pwallet_pass wallet_db

status: ## Show container status
	docker compose ps

clean: ## Stop containers and remove volumes
	docker compose down -v
