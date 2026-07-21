COMPOSE ?= docker compose
CADDY_HTTP_PORT ?= 8090

.PHONY: up down build logs health core-shell core-analyse core-quality core-test core-test-pgsql core-migrate sicodesk-shell sicodesk-test sicodesk-migrate legacy-shell legacy-test legacy-test-es legacy-test-sp legacy-test-matrix legacy-migrate

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
	curl -fsS http://localhost:$(CADDY_HTTP_PORT)/legacy/

core-shell:
	$(COMPOSE) exec sicode-core bash

core-analyse:
	$(COMPOSE) exec sicode-core vendor/bin/phpstan analyse --memory-limit=512M

core-quality:
	$(COMPOSE) exec sicode-core composer validate --strict
	$(COMPOSE) exec sicode-core vendor/bin/pint --test
	$(COMPOSE) exec sicode-core vendor/bin/phpstan analyse --memory-limit=512M
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

legacy-shell:
	$(COMPOSE) exec sicode-legacy bash

legacy-test:
	$(COMPOSE) exec -e APP_ENV=testing sicode-legacy php artisan test --env=testing

legacy-test-es:
	$(COMPOSE) exec -e APP_ENV=testing -e LEGACY_TEST_DATABASE_ALLOWED=true -e SICODE_UNIT=es -e CORE_LAUNCH_CONTEXT=ES sicode-legacy php artisan test tests/Unit/SicodeMultiUnitRuntimeTest.php tests/Feature/CoreLaunchUnitContextTest.php tests/Unit/LegacyDumpDatabaseGuardTest.php tests/Feature/CoreLaunchConsumerTest.php tests/Feature/CoreProvisioningEndpointTest.php tests/Feature/ProductionCompanyContextTest.php tests/Feature/WorkReportCompanyContextTest.php --env=testing

legacy-test-sp:
	$(COMPOSE) exec -e APP_ENV=testing -e LEGACY_TEST_DATABASE_ALLOWED=true -e SICODE_UNIT=sp -e CORE_LAUNCH_CONTEXT=SP sicode-legacy php artisan test tests/Unit/SicodeMultiUnitRuntimeTest.php tests/Feature/CoreLaunchUnitContextTest.php tests/Unit/LegacyDumpDatabaseGuardTest.php tests/Feature/CoreLaunchConsumerTest.php tests/Feature/CoreProvisioningEndpointTest.php tests/Feature/ProductionCompanyContextTest.php tests/Feature/WorkReportCompanyContextTest.php --env=testing

legacy-test-matrix:
	$(MAKE) legacy-test-es
	$(MAKE) legacy-test-sp

legacy-migrate:
	$(COMPOSE) exec sicode-legacy php artisan migrate
