COMPOSE ?= docker compose
CADDY_HTTP_PORT ?= 8090

.PHONY: up down build logs health core-shell core-analyse core-quality core-test core-test-pgsql core-migrate sicodesk-shell sicodesk-test sicodesk-migrate legacy-shell legacy-test legacy-test-es legacy-test-sp legacy-test-matrix legacy-sp-e2e legacy-sp-e2e-clean legacy-sp-e2e-verify legacy-migrate legacy-es-up legacy-es-down legacy-es-logs legacy-es-shell legacy-es-smoke legacy-es-db-inspect legacy-es-schema-diff

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

legacy-sp-e2e:
	bash scripts/e2e/legacy-sp-lifecycle.sh

legacy-sp-e2e-clean:
	$(COMPOSE) exec -e APP_ENV=testing -e SICODE_E2E_ALLOWED=true -e LEGACY_TEST_DATABASE_ALLOWED=true -e SICODE_UNIT=sp -e SICODE_IDENTITY_MODE=provisioning -e CORE_LAUNCH_CONTEXT=SP sicode-legacy php artisan legacy:e2e:sp-fixtures clean

legacy-sp-e2e-verify:
	$(COMPOSE) exec -e APP_ENV=testing -e SICODE_E2E_ALLOWED=true -e LEGACY_TEST_DATABASE_ALLOWED=true -e SICODE_UNIT=sp -e SICODE_IDENTITY_MODE=provisioning -e CORE_LAUNCH_CONTEXT=SP sicode-legacy php artisan legacy:e2e:sp-fixtures inspect

legacy-migrate:
	$(COMPOSE) exec sicode-legacy php artisan migrate

legacy-es-up:
	$(COMPOSE) up -d sicode-legacy-es

legacy-es-down:
	$(COMPOSE) stop sicode-legacy-es

legacy-es-logs:
	$(COMPOSE) logs -f sicode-legacy-es

legacy-es-shell:
	$(COMPOSE) exec sicode-legacy-es bash

legacy-es-smoke:
	$(COMPOSE) exec sicode-legacy-es php artisan tinker --execute="echo 'Unit: '.app(\App\Support\CurrentUnit::class)->value()->value.PHP_EOL; echo 'IdentityMode: '.app(\App\Support\IdentityMode::class)->value.PHP_EOL; echo 'Database: '.config('database.connections.mysql.database').PHP_EOL; echo 'Productions: '.\App\Models\Production::count().PHP_EOL; echo 'WorkReports: '.\App\Models\WorkReport::count().PHP_EOL;"

legacy-es-db-inspect:
	docker exec -i tools_mariadb mariadb -usicode -psicode sicode -e "SELECT (SELECT COUNT(*) FROM migrations) AS migrations, (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM companies) AS companies, (SELECT COUNT(*) FROM productions) AS productions, (SELECT COUNT(*) FROM work_reports) AS work_reports;"

legacy-es-schema-diff:
	docker exec -i tools_mariadb mariadb -usicode -psicode sicode -e "SHOW TABLES LIKE 'core_%';"
