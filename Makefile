COMPOSE ?= docker compose
CADDY_HTTP_PORT ?= 8090

.PHONY: up down build logs health core-shell core-analyse core-quality core-test core-test-pgsql core-migrate sicodesk-shell sicodesk-test sicodesk-migrate legacy-shell legacy-test legacy-test-es legacy-test-sp legacy-test-matrix legacy-sp-e2e legacy-sp-e2e-clean legacy-sp-e2e-verify legacy-migrate legacy-es-up legacy-es-down legacy-es-logs legacy-es-shell legacy-es-smoke legacy-es-db-inspect legacy-es-schema-diff legacy-runtime-up legacy-runtime-down legacy-redis-inspect legacy-runtime-isolation-test legacy-runtime-clear-ephemeral legacy-runtime-smoke legacy-sp-clean-up legacy-sp-clean-down legacy-sp-clean-migrate legacy-sp-clean-smoke legacy-sp-clean-e2e legacy-snapshot-up legacy-snapshot-down legacy-snapshot-inspect

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
	$(COMPOSE) exec -e APP_ENV=testing -e LEGACY_TEST_DATABASE_ALLOWED=true -e SICODE_UNIT=es -e CORE_LAUNCH_CONTEXT=ES -e SICODE_ISOLATION_GUARD_ENABLED=false sicode-legacy php artisan test tests/Unit/SicodeMultiUnitRuntimeTest.php tests/Feature/CoreLaunchUnitContextTest.php tests/Unit/LegacyDumpDatabaseGuardTest.php tests/Feature/CoreLaunchConsumerTest.php tests/Feature/CoreProvisioningEndpointTest.php tests/Feature/ProductionCompanyContextTest.php tests/Feature/WorkReportCompanyContextTest.php --env=testing

legacy-test-sp:
	$(COMPOSE) exec -e APP_ENV=testing -e LEGACY_TEST_DATABASE_ALLOWED=true -e SICODE_UNIT=sp -e CORE_LAUNCH_CONTEXT=SP -e SICODE_ISOLATION_GUARD_ENABLED=false sicode-legacy php artisan test tests/Unit/SicodeMultiUnitRuntimeTest.php tests/Feature/CoreLaunchUnitContextTest.php tests/Unit/LegacyDumpDatabaseGuardTest.php tests/Feature/CoreLaunchConsumerTest.php tests/Feature/CoreProvisioningEndpointTest.php tests/Feature/ProductionCompanyContextTest.php tests/Feature/WorkReportCompanyContextTest.php --env=testing

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

legacy-runtime-up:
	$(COMPOSE) up -d redis
	$(COMPOSE) up -d sicode-legacy-es
	$(COMPOSE) up -d sicode-legacy

legacy-runtime-down:
	$(COMPOSE) stop sicode-legacy sicode-legacy-es

legacy-redis-inspect:
	@echo "--- ES (sicode:legacy:es:, DBs 0-3) ---"
	$(COMPOSE) exec redis redis-cli -n 0 dbsize
	$(COMPOSE) exec redis redis-cli -n 1 --scan --pattern 'sicode:legacy:es:cache:*'
	$(COMPOSE) exec redis redis-cli -n 2 --scan --pattern 'sicode:legacy:es:session:*'
	$(COMPOSE) exec redis redis-cli -n 3 --scan --pattern 'sicode:legacy:es:queue:*'
	@echo "--- SP (sicode:legacy:sp:, DBs 4-7) ---"
	$(COMPOSE) exec redis redis-cli -n 4 dbsize
	$(COMPOSE) exec redis redis-cli -n 5 --scan --pattern 'sicode:legacy:sp:cache:*'
	$(COMPOSE) exec redis redis-cli -n 6 --scan --pattern 'sicode:legacy:sp:session:*'
	$(COMPOSE) exec redis redis-cli -n 7 --scan --pattern 'sicode:legacy:sp:queue:*'
	@echo "--- Snapshot (sicode:legacy:snapshot:, DBs 8-11) ---"
	$(COMPOSE) --profile snapshot exec redis redis-cli -n 8 dbsize
	$(COMPOSE) --profile snapshot exec redis redis-cli -n 9 --scan --pattern 'sicode:legacy:snapshot:cache:*'
	$(COMPOSE) --profile snapshot exec redis redis-cli -n 10 --scan --pattern 'sicode:legacy:snapshot:session:*'
	$(COMPOSE) --profile snapshot exec redis redis-cli -n 11 --scan --pattern 'sicode:legacy:snapshot:queue:*'

legacy-runtime-isolation-test:
	$(COMPOSE) exec -e APP_ENV=testing sicode-legacy-es php artisan test tests/Unit/RuntimeIsolationGuardTest.php --env=testing
	$(COMPOSE) exec -e APP_ENV=testing sicode-legacy php artisan test tests/Unit/RuntimeIsolationGuardTest.php --env=testing
	$(COMPOSE) exec -e APP_ENV=testing -e LEGACY_TEST_REDIS_ALLOWED=true sicode-legacy-es php artisan test tests/Feature/RedisRuntimeIsolationTest.php --env=testing
	$(COMPOSE) exec -e APP_ENV=testing -e LEGACY_TEST_REDIS_ALLOWED=true sicode-legacy php artisan test tests/Feature/RedisRuntimeIsolationTest.php --env=testing

# Limpa somente cache, sessoes, views compiladas e as chaves Redis do
# namespace local (por prefixo, via SCAN+DEL). NUNCA usa FLUSHALL/FLUSHDB,
# porque o Redis e compartilhado entre aplicacoes. Nao apaga storage/app,
# storage/logs nem dados de banco.
legacy-runtime-clear-ephemeral:
	docker run --rm -v "$$(pwd)/apps/sicode-legacy/storage:/storage" alpine sh -c '\
		find /storage/framework/cache -type f ! -name ".gitignore" -delete; \
		find /storage/framework/sessions -type f ! -name ".gitignore" -delete; \
		find /storage/framework/views -type f ! -name ".gitignore" -delete'
	for unit_db in "es:1" "es:2" "es:3" "sp:5" "sp:6" "sp:7"; do \
		unit=$${unit_db%%:*}; db=$${unit_db##*:}; \
		$(COMPOSE) exec redis sh -c "redis-cli -n $$db --scan --pattern 'sicode:legacy:'$$unit':*' | xargs -r -n 100 redis-cli -n $$db del"; \
	done

legacy-runtime-smoke:
	$(COMPOSE) exec sicode-legacy-es php artisan tinker --execute="echo 'ES cache driver: '.config('cache.default').PHP_EOL; echo 'ES redis prefix: '.config('database.redis.cache.options.prefix').PHP_EOL;"
	$(COMPOSE) exec sicode-legacy php artisan tinker --execute="echo 'SP cache driver: '.config('cache.default').PHP_EOL; echo 'SP redis prefix: '.config('database.redis.cache.options.prefix').PHP_EOL;"
	curl -fsS http://localhost:$${SICODE_LEGACY_ES_HTTP_PORT:-8084}/ > /dev/null && echo "ES http OK"
	curl -fsS http://localhost:$${SICODE_LEGACY_HTTP_PORT:-8083}/ > /dev/null && echo "SP http OK"

# ──────────────────────────────────────────────────────────────────────────
# SP Clean — instância canônica São Paulo (sicode_sp)
# ──────────────────────────────────────────────────────────────────────────

legacy-sp-clean-up:
	$(COMPOSE) up -d sicode-legacy-sp-mariadb
	$(COMPOSE) up -d sicode-legacy

legacy-sp-clean-down:
	$(COMPOSE) stop sicode-legacy sicode-legacy-sp-mariadb

# Exige confirmacao explicita para evitar migrate acidental
legacy-sp-clean-migrate:
	@echo ""
	@echo "AVISO: Este alvo executa php artisan migrate no banco sicode_sp (SP Clean)."
	@echo "       Certifique-se de que o banco esta no estado correto antes de prosseguir."
	@echo ""
	@read -p "Digite 'sim' para continuar: " CONFIRM && [ "$$CONFIRM" = "sim" ] || (echo "Cancelado."; exit 1)
	$(COMPOSE) exec sicode-legacy php artisan migrate --force

legacy-sp-clean-smoke:
	$(COMPOSE) exec sicode-legacy php artisan tinker --execute="\
		echo 'Unit: '.app(\App\Support\CurrentUnit::class)->value()->value.PHP_EOL;\
		echo 'IdentityMode: '.app(\App\Support\IdentityMode::class)->value.PHP_EOL;\
		echo 'Database: '.config('database.connections.mysql.database').PHP_EOL;\
		echo 'Users: '.\App\Models\User::count().PHP_EOL;\
		echo 'Companies: '.\App\Models\Company::count().PHP_EOL;\
		echo 'IdentityLinks: '.\App\Models\CoreIdentityLink::count().PHP_EOL;\
		echo 'OrgLinks: '.\App\Models\CoreOrganizationLink::count().PHP_EOL;\
		echo 'Guard: '.config('sicode.isolation.expected_database').PHP_EOL;\
		"

legacy-sp-clean-e2e:
	bash scripts/e2e/legacy-sp-lifecycle.sh

# ──────────────────────────────────────────────────────────────────────────
# Snapshot — banco historico de regressao (sicode_legacy, perfil snapshot)
# Nenhum destes alvos apaga o volume snapshot.
# ──────────────────────────────────────────────────────────────────────────

legacy-snapshot-up:
	$(COMPOSE) --profile snapshot up -d sicode-legacy-snapshot-mariadb
	$(COMPOSE) --profile snapshot up -d sicode-legacy-snapshot

legacy-snapshot-down:
	$(COMPOSE) --profile snapshot stop sicode-legacy-snapshot sicode-legacy-snapshot-mariadb

legacy-snapshot-inspect:
	docker exec ecosystem-sicode-legacy-snapshot-mariadb-1 mariadb \
		-usicode_legacy -plegacy_dev_password sicode_legacy \
		-e "SELECT \
			(SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='sicode_legacy') AS tables, \
			(SELECT COUNT(*) FROM migrations) AS migrations, \
			(SELECT COUNT(*) FROM users) AS users, \
			(SELECT COUNT(*) FROM companies) AS companies, \
			(SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) FROM information_schema.TABLES WHERE TABLE_SCHEMA='sicode_legacy') AS total_mb;"
