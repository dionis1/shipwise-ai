DC = docker compose
.PHONY: setup migrate test backend-test frontend-test quality bootstrap reset test-database e2e frontend-ready
setup:
	test -f .env || cp .env.example .env
	test -f apps/backend/.env || cp apps/backend/.env.example apps/backend/.env
	$(DC) run --rm --no-deps --user $$(id -u):$$(id -g) -e COMPOSER_HOME=/tmp/composer backend composer install --no-interaction
	$(DC) run --rm --no-deps -e npm_config_cache=/tmp/npm frontend npm ci
migrate:
	$(DC) exec --user $$(id -u):$$(id -g) backend php bin/console doctrine:migrations:migrate --no-interaction
test-database:
	$(DC) exec --user $$(id -u):$$(id -g) -e APP_ENV=test backend php bin/console doctrine:database:create --if-not-exists --no-interaction
backend-test:
	$(DC) exec --user $$(id -u):$$(id -g) -e APP_ENV=test backend php bin/console doctrine:database:create --if-not-exists --no-interaction
	$(DC) exec --user $$(id -u):$$(id -g) -e APP_ENV=test backend php bin/console doctrine:migrations:migrate --no-interaction
	$(DC) exec --user $$(id -u):$$(id -g) -e APP_ENV=test backend vendor/bin/phpunit
frontend-ready:
	$(DC) up -d --wait --wait-timeout 120 frontend
frontend-test: frontend-ready
	$(DC) exec frontend npm test
test: backend-test frontend-test
quality: frontend-ready
	$(DC) exec --user $$(id -u):$$(id -g) backend composer validate --strict
	$(DC) exec --user $$(id -u):$$(id -g) backend composer analyse
	$(DC) exec --user $$(id -u):$$(id -g) backend composer lint
	$(DC) exec --user $$(id -u):$$(id -g) backend php bin/console lint:container
	$(DC) exec --user $$(id -u):$$(id -g) backend php bin/console lint:yaml config
	$(DC) exec --user $$(id -u):$$(id -g) backend php bin/console doctrine:schema:validate --skip-sync
	$(DC) exec frontend npm run typecheck
	$(DC) exec frontend npm run i18n:check
	$(DC) exec frontend npm run lint
	$(DC) exec frontend npm run format:check
	$(DC) exec frontend npm run build
	$(DC) exec frontend npm run api:validate
	$(DC) exec frontend npm audit --audit-level=moderate
	$(DC) exec --user $$(id -u):$$(id -g) backend composer audit
bootstrap:
	$(DC) exec --user $$(id -u):$$(id -g) backend php bin/console app:bootstrap-admin $(email) "$(name)"
reset:
	@echo 'This removes the local development database. Type RESET to continue:'
	@read answer; test "$$answer" = RESET
	$(DC) exec backend php bin/console doctrine:database:drop --force --if-exists
	$(DC) exec backend php bin/console doctrine:database:create
	$(MAKE) migrate
e2e:
	bash scripts/e2e.sh
