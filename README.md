# ShipWise AI

A multi-carrier shipping marketplace foundation built as a Symfony 7.4 / PHP 8.4 modular monolith with a Vue 3 / TypeScript client. Phase 1 implements identity, database-backed access control, staff administration, customer profiles and audit history. Carrier quotes, purchases, payments, labels, tracking and AI advice are future capabilities; no simulated business metrics are presented as real.

## Local development

Requirements: Docker Engine, Compose **2.24.4+** (override tags), Make; OpenSSL for E2E automation. Containers use PHP 8.4, Node 22, PostgreSQL 17, Redis 7, Nginx and Mailpit. No paid services or carrier credentials are required.

```bash
git clone git@github.com:dionis1/shipwise-ai.git
cd shipwise-ai
cp .env.example .env
cp apps/backend/.env.example apps/backend/.env
docker compose build backend
make setup
docker compose up -d
make migrate
```

Application: http://localhost:8080 · Mailpit: http://localhost:8025. First startup installs frontend dependencies; wait until the frontend health check is healthy. Run `docker compose ps` and `docker compose logs frontend` for progress. Set `LOCAL_UID` / `LOCAL_GID` when your user is not UID/GID 1000. Backend CLI commands run as the current user. FPM workers use the unprivileged www-data user mapped to LOCAL_UID/LOCAL_GID at build time; rebuild after changing these values.

All `.env` files, dependency directories, build output, browser reports and ZIP archives are ignored. Examples contain **local-only** database defaults; replace them before deployment. Never store production credentials in Git. Set APP_PORT and APP_URL together when changing ports. The root environment configures Compose; backend defaults are in `apps/backend/.env.example`.

```bash
make bootstrap email=owner@example.com name="Platform Owner"
```

Enter a unique password through the hidden interactive prompt. No default staff password exists. Bootstrap stops once an active Super Administrator exists. Use `/login` for staff and customer accounts; department users land in their permitted workspace. Customers register at `/register`, verify the single-use link in Mailpit, and then sign in. Staff invitations and password-reset messages also arrive in Mailpit. Links keep the token in a URL fragment, and the client clears it immediately.

For optional local demo accounts run `docker compose exec backend php bin/console app:seed-demo-users`. It creates active dummy accounts for all ten roles, prints a generated password once, skips existing accounts and refuses any environment except `dev`. Keep the printed credentials outside Git. These accounts bypass onboarding intentionally for local testing; they are not production bootstrap credentials.

## Commands

| Command | Purpose |
| --- | --- |
| `make setup` | Install locked dependencies and initialize local environment files |
| `make migrate` | Apply committed PostgreSQL migrations |
| `make test` | PHPUnit against isolated `_test` database and Vitest |
| `make quality` | Composer, PHPStan, style, Symfony, Doctrine mapping, TypeScript, ESLint, build, API lint and audits |
| `make e2e` | Separate ephemeral Compose project, generated test password, Playwright workflows; destroys its own volumes on exit |
| `make reset` | Confirm and reset **development** database only |
| `docker compose exec frontend npm run api:generate` | Regenerate TypeScript from OpenAPI |
| `docker compose down` | Stop local services while preserving development data |

Test credentials are generated for the E2E run. The seed script refuses any database other than `shipwise_e2e`. PHPUnit refuses any database whose name does not end with `_test`. Development and test database data must never be used as production fixtures.

## Repository structure

```text
apps/backend/{config,migrations,public,src,tests}
  src/Identity/{Domain,Application,Infrastructure,Presentation}
  src/AccessControl/{Domain,Application,Infrastructure}
  src/Administration/{Application,Infrastructure,Presentation}
  src/Audit/{Application,Infrastructure}
  src/Shared/{Domain,Application,Infrastructure,Presentation}
apps/frontend/src/{api,components,features,router,stores,views}
apps/frontend/tests/e2e
infra/{docker,nginx}
docs/{architecture,adr,api,security}
scripts/
.github/workflows/
```

The domain has no Symfony or Doctrine dependencies. ORM XML mapping lives in infrastructure configuration. Application workflows depend on repository, mail, hashing and transaction ports. Doctrine ORM persists accounts; DBAL handles relational RBAC, tokens, append-only application audit writes and administrative read models. Mutations and audit entries share a transaction. API controllers translate validated request input into application operations and return explicit profile/read DTOs; password hashes and tokens are never exposed.

## Routes and API

Public: `/`, `/login`, `/register`, `/verify-email`, `/forgot-password`, `/reset-password`, `/accept-invitation`.
Customer: `/app`, `/app/profile`, `/app/settings`.
Staff: `/admin`, `/admin/staff`, `/admin/access-control`, `/admin/audit`, `/admin/qa`, `/admin/finance`, `/admin/marketing`, `/admin/operations`, `/admin/support`, `/admin/carriers`.

The versioned same-origin API is defined in [OpenAPI](docs/api/openapi.json). Generated client models are in `apps/frontend/src/api/generated.ts`. See [API contracts](docs/architecture/api-contracts.md) and [role matrix](docs/architecture/role-matrix.md). Roles are SUPER_ADMIN, PLATFORM_ADMIN, OPERATIONS_MANAGER, QA_ENGINEER, FINANCE_MANAGER, MARKETING_MANAGER, CUSTOMER_SUPPORT, CARRIER_MANAGER, AUDITOR and CUSTOMER. Multiple staff roles are supported. Backend voters enforce explicit permissions; route guards and navigation reuse the current session's permission list.

## Verification and delivery status

See [verification report](docs/verification.md) for commands actually executed, counts and limitations. CI gates every pull request with backend checks, frontend checks, dependency audits, contract synchronization and browser workflows. Configure required status checks and reviews as described in [deployment](docs/architecture/deployment.md).

This is a development foundation, **not a production-ready deployment**. Mandatory staff MFA is not implemented; staff login is deliberately disabled under `APP_ENV=prod`. See [production requirements](docs/security/production-readiness.md) before changing that guard. GDPR compliance requires a separate legal and operational review.

## Next phase

Start with approved carrier contracts, quote provenance, backend pricing in integer minor units, expiring immutable offers and a single sandbox adapter. Then add verified PSP events, transactional outbox/inbox, idempotent order purchase processing, private labels and reconciliation for unknown provider outcomes. Preserve the [future purchase safety design](docs/ARCHITECTURE.md); do not connect an AI model directly to financial or carrier purchase actions.
