.PHONY: help up down build restart logs shell-api shell-web migrate fresh test lint format workers up-prod queue-restart backup

help: ## Show available commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Start all services (development)
	docker compose up -d

workers: ## Start local queue + scheduler (profile workers)
	docker compose --profile workers up -d queue scheduler

up-prod: ## Start production-style compose overlay
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

queue-restart: ## Signal Laravel queue workers to restart
	docker compose exec api php artisan queue:restart

backup: ## Run DB + media backup + verify (API container)
	docker compose exec api php artisan ops:backup-run
	docker compose exec api php artisan ops:backup-verify --latest

down: ## Stop all services
	docker compose down

build: ## Build Docker images
	docker compose build

restart: ## Restart all services
	docker compose restart

logs: ## Tail logs from all services
	docker compose logs -f

shell-api: ## Open shell in API container
	docker compose exec api sh

shell-web: ## Open shell in Web container
	docker compose exec web sh

migrate: ## Run Laravel migrations
	docker compose exec api php artisan migrate

fresh: ## Reset database, migrate, and seed
	docker compose exec api php artisan migrate:fresh --seed

test: ## Run backend tests
	docker compose exec api php artisan test

lint: ## Lint frontend
	npm run lint --workspace=apps/web

format: ## Format codebase with Prettier
	npm run format
