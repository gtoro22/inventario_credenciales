ENVF  = --env-file .env.docker
DC    = docker compose $(ENVF)
DCDEV = docker compose $(ENVF) -f compose.yaml -f compose.dev.yaml

.PHONY: init up down logs install shell test dev dev-down
init: ## Crea .env.docker con claves generadas
	@test -f .env.docker || (cp .env.docker.example .env.docker && \
	  sed -i "s|^APP_KEY=.*|APP_KEY=base64:$$(openssl rand -base64 32)|; s|^VAULT_KEY=.*|VAULT_KEY=base64:$$(openssl rand -base64 32)|; s|^DB_PASSWORD=.*|DB_PASSWORD=$$(openssl rand -hex 16)|; s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=$$(openssl rand -hex 16)|" .env.docker && \
	  echo ".env.docker creado. Respalda VAULT_KEY en un lugar seguro.")
up: ; $(DC) up -d --build
down: ; $(DC) down
logs: ; $(DC) logs -f
install: ## Crea el superadministrador (interactivo)
	$(DC) exec app php artisan credentials:install
shell: ; $(DC) exec app sh
test: ## Tests dentro del contenedor de desarrollo
	$(DCDEV) run --rm --no-deps -e APP_ENV=testing app php artisan test
dev: ; $(DCDEV) up --build
dev-down: ; $(DCDEV) down
