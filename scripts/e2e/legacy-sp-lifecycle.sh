#!/usr/bin/env bash
set -euo pipefail

COMPOSE="${COMPOSE:-docker compose}"
RUN_ID="${SICODE_E2E_RUN_ID:-$(date +%Y%m%d%H%M%S)-$RANDOM}"
CORE_PORT="${SICODE_E2E_CORE_PORT:-$((18000 + RANDOM % 1000))}"
LEGACY_PORT="${SICODE_E2E_LEGACY_PORT:-$((19000 + RANDOM % 1000))}"
PROVISIONING_SECRET="${SICODE_E2E_PROVISIONING_SECRET:-testing_e2e_sp_provisioning_secret}"
LAUNCH_SECRET="${SICODE_E2E_LAUNCH_SECRET:-testing_e2e_sp_launch_secret}"

cleanup_servers() {
  if [[ -n "${CORE_PID:-}" ]]; then
    ${COMPOSE} exec -T sicode-core sh -lc "kill ${CORE_PID} >/dev/null 2>&1 || true" >/dev/null 2>&1 || true
  fi
  if [[ -n "${LEGACY_PID:-}" ]]; then
    ${COMPOSE} exec -T sicode-legacy sh -lc "kill ${LEGACY_PID} >/dev/null 2>&1 || true" >/dev/null 2>&1 || true
  fi
}
trap cleanup_servers EXIT

echo "E2E run_id=${RUN_ID}"
echo "E2E core_port=${CORE_PORT} legacy_port=${LEGACY_PORT}"

${COMPOSE} ps

${COMPOSE} exec -T sicode-core sh -lc "pkill -f 'artisan serve --host=0.0.0.0 --port=${CORE_PORT}' >/dev/null 2>&1 || true"
${COMPOSE} exec -T sicode-legacy sh -lc "pkill -f 'artisan serve --host=0.0.0.0 --port=${LEGACY_PORT}' >/dev/null 2>&1 || true"

CORE_PID="$(${COMPOSE} exec -T \
  -e APP_ENV=testing \
  -e SICODE_E2E_ALLOWED=true \
  -e LEGACY_TEST_DATABASE_ALLOWED=true \
  -e CORE_LAUNCH_CLIENT_SECRETS="{\"sicode-legacy-sp-e2e\":\"${LAUNCH_SECRET}\"}" \
  sicode-core sh -lc "php artisan serve --host=0.0.0.0 --port=${CORE_PORT} > storage/logs/e2e-core-${RUN_ID}.log 2>&1 & echo \$!")"

LEGACY_PID="$(${COMPOSE} exec -T \
  -e APP_ENV=testing \
  -e SICODE_E2E_ALLOWED=true \
  -e LEGACY_TEST_DATABASE_ALLOWED=true \
  -e SICODE_UNIT=sp \
  -e SICODE_IDENTITY_MODE=provisioning \
  -e SICODE_INSTANCE_CODE=sicode-legacy-sp-e2e \
  -e SICODE_INSTANCE_NAME="SICODE Legacy SP E2E" \
  -e SICODE_STORAGE_PREFIX=legacy/sp/e2e \
  -e SICODE_ISOLATION_GUARD_ENABLED=false \
  -e CORE_LAUNCH_EXCHANGE_URL="http://sicode-core:${CORE_PORT}/api/core/launch/exchange" \
  -e CORE_LAUNCH_CLIENT_IDENTIFIER=sicode-legacy-sp-e2e \
  -e CORE_LAUNCH_CLIENT_SECRET="${LAUNCH_SECRET}" \
  -e CORE_LAUNCH_REDIRECT_URI="https://sicode-legacy:${LEGACY_PORT}/core/launch/callback" \
  -e CORE_LAUNCH_CONTEXT=SP \
  -e CORE_PROVISIONING_CLIENT_SECRETS="{\"sicode-core-sp-provisioner\":\"${PROVISIONING_SECRET}\"}" \
  -e SESSION_DRIVER=file \
  sicode-legacy sh -lc "php artisan serve --host=0.0.0.0 --port=${LEGACY_PORT} > storage/logs/e2e-legacy-${RUN_ID}.log 2>&1 & echo \$!")"

sleep 3

${COMPOSE} exec -T sicode-core curl -fsS "http://127.0.0.1:${CORE_PORT}/up" >/dev/null
${COMPOSE} exec -T sicode-legacy curl -fsS "http://127.0.0.1:${LEGACY_PORT}/" >/dev/null

${COMPOSE} exec -T \
  -e APP_ENV=testing \
  -e SICODE_E2E_ALLOWED=true \
  -e LEGACY_TEST_DATABASE_ALLOWED=true \
  -e LEGACY_SP_PROVISIONING_ENABLED=true \
  -e LEGACY_SP_PROVISIONING_BASE_URL="http://sicode-legacy:${LEGACY_PORT}" \
  -e LEGACY_SP_PROVISIONING_CLIENT_ID=sicode-core-sp-provisioner \
  -e LEGACY_SP_PROVISIONING_CLIENT_SECRET="${PROVISIONING_SECRET}" \
  -e LEGACY_SP_PROVISIONING_CONTEXT=sp \
  -e SICODE_E2E_LEGACY_PORT="${LEGACY_PORT}" \
  -e CORE_LAUNCH_CLIENT_SECRETS="{\"sicode-legacy-sp-e2e\":\"${LAUNCH_SECRET}\"}" \
  sicode-core php artisan core:e2e:legacy-sp-lifecycle --run-id="${RUN_ID}"

${COMPOSE} exec -T \
  -e APP_ENV=testing \
  -e SICODE_E2E_ALLOWED=true \
  -e LEGACY_TEST_DATABASE_ALLOWED=true \
  -e SICODE_UNIT=sp \
  -e SICODE_IDENTITY_MODE=provisioning \
  -e CORE_LAUNCH_CONTEXT=SP \
  sicode-legacy php artisan legacy:e2e:sp-fixtures inspect "${RUN_ID}"

${COMPOSE} exec -T \
  -e APP_ENV=testing \
  -e SICODE_E2E_ALLOWED=true \
  -e LEGACY_TEST_DATABASE_ALLOWED=true \
  -e SICODE_UNIT=sp \
  -e SICODE_IDENTITY_MODE=provisioning \
  -e CORE_LAUNCH_CONTEXT=SP \
  sicode-legacy php artisan legacy:e2e:sp-fixtures cleanup "${RUN_ID}"

${COMPOSE} exec -T \
  -e APP_ENV=testing \
  -e SICODE_E2E_ALLOWED=true \
  -e LEGACY_TEST_DATABASE_ALLOWED=true \
  -e SICODE_UNIT=sp \
  -e SICODE_IDENTITY_MODE=provisioning \
  -e CORE_LAUNCH_CONTEXT=SP \
  sicode-legacy php artisan legacy:e2e:sp-fixtures verify-clean "${RUN_ID}"

${COMPOSE} exec -T \
  -e APP_ENV=testing \
  -e SICODE_E2E_ALLOWED=true \
  -e LEGACY_TEST_DATABASE_ALLOWED=true \
  sicode-core php artisan core:e2e:legacy-sp-lifecycle --run-id="${RUN_ID}" --cleanup-only

echo "E2E completed run_id=${RUN_ID}"
