COMPOSE ?= docker compose
CADDY_HTTP_PORT ?= 8090

.PHONY: up down build logs health core-shell core-analyse core-quality core-test core-test-pgsql core-migrate sicodesk-shell sicodesk-test sicodesk-migrate

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

logs:
	$(COMPOSE) logs -f

health:
	$(COMPOSE) ps
	curl -fsS http://localhost:$(CADDY_HTTP_PORT)/core/health
	curl -fsS http://localhost:$(CADDY_HTTP_PORT)/sicodesk/health

core-shell:
	$(COMPOSE) exec sicode-core bash

core-analyse:
	$(COMPOSE) exec sicode-core vendor/bin/phpstan analyse

core-quality:
	$(COMPOSE) exec sicode-core composer validate --strict
	$(COMPOSE) exec sicode-core vendor/bin/pint --test
	$(COMPOSE) exec sicode-core vendor/bin/phpstan analyse
	$(MAKE) core-test
	$(MAKE) core-test-pgsql

core-test:
	$(COMPOSE) exec -e APP_ENV=testing sicode-core php artisan test --env=testing

core-test-pgsql:
	$(COMPOSE) exec -e APP_ENV=testing sicode-core php artisan test tests/Feature/CoreSchemaConstraintsTest.php --env=testing

core-migrate:
	$(COMPOSE) exec sicode-core php artisan migrate

sicodesk-shell:
	$(COMPOSE) exec sicodesk bash

sicodesk-test:
	$(COMPOSE) exec -e APP_ENV=testing sicodesk php artisan test --env=testing

sicodesk-migrate:
	$(COMPOSE) exec sicodesk php artisan migrate
