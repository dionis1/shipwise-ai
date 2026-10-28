#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export E2E_PASSWORD="$(openssl rand -hex 24)"
e2e=(docker compose -p shipwise-e2e -f compose.yaml -f compose.e2e.yaml)
cleanup() { "${e2e[@]}" down --volumes; }
trap cleanup EXIT
"${e2e[@]}" up -d --build backend database redis mailpit frontend proxy
for attempt in $(seq 1 60); do
  if "${e2e[@]}" exec -T backend php -r 'exit(is_file("/app/vendor/autoload.php")?0:1);' >/dev/null 2>&1; then break; fi
  sleep 1
done
"${e2e[@]}" exec -T --user "$(id -u):$(id -g)" backend php bin/console doctrine:migrations:migrate --no-interaction
"${e2e[@]}" exec -T -e E2E_PASSWORD backend php /scripts/seed-e2e.php
for attempt in $(seq 1 60); do
  if "${e2e[@]}" exec -T frontend node -e 'fetch("http://proxy/login").then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))'  >/dev/null; then break; fi
  sleep 1
done
"${e2e[@]}" run --rm playwright npm run test:e2e
