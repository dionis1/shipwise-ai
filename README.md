# ShipWise AI

A multi-carrier shipping marketplace foundation built as a Symfony 7.4 / PHP 8.4 modular monolith with a Vue 3 / TypeScript client. Phase 1 implements identity, database-backed access control, staff administration, customer profiles and audit history. Phase 2 adds preliminary comparison from clearly labeled development tariffs. Live carrier integration, purchases, payments, labels, tracking and AI advice remain future capabilities; no simulated business metrics are presented as real.

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

## Interface and languages

The light interface supports English, German and French on every existing route. Use the language selector in the public or account header. Anonymous selection is saved locally; after login the account preference takes precedence and changes are saved without signing out. Registration and staff invitations choose the recipient language; verification, reset and invitation emails use that preference.

Run `make migrate` after pulling this change to add the account locale column. See the [redesign delivery report](docs/phases/PHASE_1_6_UI_REDESIGN_I18N.md) and [design and translation guide](docs/design/README.md) for components, tokens, licenses and instructions for adding a language.

## Commands

| Command | Purpose |
| --- | --- |
| `make setup` | Install locked dependencies and initialize local environment files |
| `make migrate` | Apply committed PostgreSQL migrations |
| `make test` | PHPUnit against isolated `_test` database and Vitest |
| `make quality` | Composer, PHPStan, style, Symfony, Doctrine mapping, TypeScript, translation coverage, ESLint, build, API lint and audits |
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

## Phase 1.5 — Production readiness gate

Before exposing customer accounts or staff administration to real users, implement the [Phase 1.5 production readiness requirements](docs/phases/PHASE_1_5_PRODUCTION_READINESS.md). This is a separate, **planned** release-hardening phase, not completed functionality. The current `compose.yaml` is for development only and staff authentication under `APP_ENV=prod` remains intentionally blocked until mandatory MFA is enforced and tested. Phase 1.5 excludes carrier quoting, purchases, payments, labels and AI.

## Phase 2 — Carrier comparison and quoting

Open `/compare` to quote one parcel in EN/DE/FR using the deterministic development provider. Run `make migrate` after pulling. Seeded routes are Germany → Germany/France; kilograms/centimeters convert to integer grams/millimeters. Prices include a visibly labeled synthetic tax scenario and are not real carrier commitments. DHL, DPD, GLS and UPS remain disabled and unconfigured.

Offers expire after 15 minutes and preliminary records are retained for at most 24 hours. Run `docker compose exec backend php bin/console app:purge-quotes` at least hourly in any deployed quotation environment. Guest tokens stay in memory and require the original session; account reads require ownership.

Authorized staff manage availability and append tariff versions at `/admin/carriers`; only Super Administrators manage pricing rules. See the [Phase 2 implementation guide](docs/phases/PHASE_2_CARRIER_COMPARISON_QUOTING.md) for architecture, exact pricing/rounding, source limitations, administration, provider extension and tests. [Example quotation](docs/api/phase-2-example.json) contains synthetic test data only.

The next phase requires approved carrier contracts and legally verified commercial tax scenarios before any live offers. Purchase work must add verified PSP events, transactional outbox/inbox, idempotent order purchase processing, private labels and reconciliation for unknown provider outcomes. Preserve the [future purchase safety design](docs/ARCHITECTURE.md); do not connect an AI model directly to financial or carrier purchase actions.
