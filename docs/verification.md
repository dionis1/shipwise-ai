# Verification report

Executed locally on 2026-10-28 using Docker (PHP 8.4, Node 22, PostgreSQL 17, Redis and Mailpit). The table below records the original local results; independently, GitHub Actions subsequently completed all three CI jobs successfully. Neither local nor CI success proves production readiness.

| Check | Observed result |
| --- | --- |
| `make test` | Passed: PHPUnit **35 tests / 110 assertions**, Vitest **17 tests / 4 files** |
| `bash scripts/e2e.sh` | Passed: **5 Playwright workflows**, isolated database and generated credentials, automatic volume cleanup |
| `make quality` | Passed: Composer validation, PHPStan level 6, PHP-CS-Fixer, Symfony container/YAML lint, Doctrine mapping, TypeScript, ESLint with zero warnings, Prettier, production frontend build, OpenAPI lint and dependency audits |
| PHP image build / Compose configuration | Passed, including the separate E2E override and unprivileged FPM worker identity |
| PostgreSQL migrations | Applied successfully to development, isolated PHPUnit and fresh E2E databases |
| API route comparison | **26 operations** match the Symfony router and OpenAPI contract |
| OpenAPI TypeScript regeneration | Generated from the committed contract; generated models are checked for drift |
| Dependency audits | Composer: no advisories; npm: zero known vulnerabilities at the time of execution |
| Browser inspection | Landing page and login rendered in the in-app browser; desktop/mobile behavior also covered by Playwright |
| Git exclusions | ZIP archive, actual environment files, dependency/build directories and generated test reports remain outside Git |

The browser workflows cover customer registration, real Mailpit email verification, login, profile editing, logout and protected-route redirect; Super Administrator navigation, invitation, authorized role assignment, audit visibility and invitation acceptance; Finance/QA department access and rejected unauthorized API/UI access; mobile navigation and overflow.

Backend tests include permission separation, customer/staff boundaries, CSRF, input allowlists, generic recovery responses, hashed expiring single-use tokens, password changes, current-session revocation after own-role/password changes, last-active-administrator protection, atomic invalid-invitation rollback, staff search/status changes, real dashboard counts, sanitized audit filters and refusal of demo account generation outside development.

## GitHub Actions verification (2026-10-28)

The first remote [Quality gates run](https://github.com/dionis1/shipwise-ai/actions/runs/40687001753) failed during PHP-CS-Fixer because of formatting differences in three backend files. Its PHPUnit tests, frontend job and E2E job had passed. The [follow-up workflow](https://github.com/dionis1/shipwise-ai/actions/runs/40687924861) on commit [`c69d9de`](https://github.com/dionis1/shipwise-ai/commit/c69d9de83d68b30be8478039c1545c7bb841043f) completed successfully with all three jobs green:

| CI job | Verified remote result |
| --- | --- |
| Backend | Passed: PostgreSQL migration, PHPUnit **35 tests / 110 assertions**, Composer validation, PHPStan, PHP-CS-Fixer, Symfony configuration and Doctrine checks, API route contract and Composer audit |
| Frontend | Passed: install, ESLint, Prettier, TypeScript, Vitest, production build, OpenAPI lint/client regeneration and npm audit |
| E2E | Passed: isolated Playwright browser workflows |

GitHub remote CI is independently confirmed. Repository branch protection, production deployment, security penetration testing and compliance/a11y audits are still outstanding. Subsequent commits must pass the same checks.

## Scope and limitations

- Mandatory staff MFA is incomplete. `APP_ENV=prod` denies staff login and refresh of staff sessions. This is not a production-ready authentication release.
- Production deployment, repository branch protection, penetration testing and a full accessibility/compliance review were **not executed**. Remote GitHub Actions execution is recorded above.
- Audit APIs are read-only, but production database privilege separation, off-host retention and operational monitoring still require deployment work.
- Email is synchronous; a durable outbox and resend/recovery operations are production follow-ups. Shared session infrastructure is required before horizontal deployment.
- Carrier quotations/purchases, payments, labels, tracking and AI recommendations intentionally remain future capabilities. Department empty states do not pretend to implement them.
- Local demo accounts exist only because they were explicitly requested. Their generated credentials are not in source control. E2E fixtures are isolated and destroyed after each run.

See `README.md`, `docs/architecture/` and `docs/security/production-readiness.md` for commands, module boundaries, exact roles/permissions, migration ownership and release blockers.

## Phase 1.6 local verification (2026-10-28)

| Check | Observed result |
| --- | --- |
| `make test` | Passed: PHPUnit **48 tests / 188 assertions** and Vitest **33 tests / 7 files** |
| `make quality` | Passed: backend static/configuration/mapping/style checks, TypeScript, ESLint, Prettier, production build, OpenAPI lint and dependency audits |
| Translation coverage | **305 matching keys** in each EN/DE/FR catalog, placeholder and static Vue-copy checks passed |
| `bash scripts/e2e.sh` | **16 Playwright workflows passed** on an isolated database; EN/DE/FR accounts, real localized Mailpit links, all existing routes, invitation/role/suspension flows and last-admin safeguard |
| Accessibility and layout | Tested 390px, 768px and 1440px viewports, no horizontal overflow in covered routes, Tab containment/Escape/focus restoration; no Axe violations in scanned forms, settings, audit and dialogs |
| Security preservation | Existing CSRF, session revocation, RBAC and token tests passed; production staff MFA restriction remains |

Browser screenshots are generated at `apps/frontend/test-results/redesign-audit-{390,768,1440}.png` and uploaded by CI as short-lived artifacts. They are not committed. Automated checks and local browser inspection do not constitute a comprehensive accessibility, penetration or production deployment audit. Phase 1.5 remains planned and Phase 2 has not started.

The first remote redesign run detected PHP formatting differences in three new/edited files despite the cached local lint result. Those differences were corrected and the Composer lint script now disables its cache so local and fresh CI inspect every file. The [corrected GitHub Actions run](https://github.com/dionis1/shipwise-ai/actions/runs/40702252940) on [`afcbbab`](https://github.com/dionis1/shipwise-ai/commit/afcbbab113773455ef5153ddb6ced8212f420de9) completed successfully: backend, frontend and E2E jobs all passed. The pull request is [#1](https://github.com/dionis1/shipwise-ai/pull/1), targeting the repository default branch `feat/phase-one-foundation`; it remains unmerged. Subsequent documentation-only commits are subject to the same CI checks.

## Phase 2 verification (2026-10-29)

Implementation is based on verified main `6b9fe97`. No live carrier credentials, commercial tariffs or legally verified tax scenarios were supplied. All offers in this phase come from the explicitly labeled deterministic development source.

| Check | Actual local result |
| --- | --- |
| Backend | **83 tests / 278 assertions** passed, including all Phase 1 regressions, canonical parcel validation, integer pricing, rounding, currencies, provider contracts, SQL persistence/constraints, guest/owner access, CSRF, idempotency/retention, tariff versions, pricing precedence, RBAC, audit and expiry |
| Frontend | **46 tests / 9 files** passed, including exact decimal-string conversion, comparison loading/validation/partial/empty states and backend-price sorting |
| Browser | **25 workflows** passed, including EN/DE/FR comparison and existing Phase 1/1.6 regressions; guest/customer access, availability administration, invalid provider response isolation and offer expiry |
| Accessibility/layout | Axe scans had no violations in covered comparison views/drawers and existing covered views; 390/768/1440px comparison, no horizontal overflow, native-dialog keyboard behavior |
| Quality | Complete `make quality` passed: PHPStan, fresh PHP formatting, Symfony config/container/mapping checks, TypeScript, ESLint, Prettier, production build, OpenAPI and audits |
| Translation coverage | **443 matching keys per locale**, placeholder consistency, untranslated Vue text and unknown static translation-key checks |
| Contract | **37 operations** synchronized with Symfony, generated client synchronized |
| Migrations | Both additive Phase 2 migrations applied locally and on fresh isolated E2E PostgreSQL databases |

The first browser attempts found a required-label selector issue and use of secure-context-only randomUUID on the internal HTTP hostname. Both were corrected; request keys now use getRandomValues. A repeat run additionally exposed an availability-toggle race during catalog reload; actions now stay disabled until the refresh finishes, and the browser test waits for confirmed persistence. Visual inspection found a missing UTC resource, now fixed and covered by the stronger translation checker. SQL tests also verified actual PostgreSQL check/foreign-key errors and append-only snapshots.

Composer audit retains main's existing, narrowly scoped temporary exception for the inaccurate symfony/mime advisory metadata; it is not a new broad exception. Its documented reevaluation deadline remains 2026-11-04. No new dependency or audit exception was introduced in Phase 2.

Screenshots are generated at `apps/frontend/test-results/phase2-compare-{390,768,1440}.png` and uploaded with the existing CI interface artifacts; they are ignored by Git. Guest records require scheduled `app:purge-quotes` cleanup; access stops after the retention deadline independently. Automated scans do not prove full accessibility, commercial legality or production readiness. Phase 1.5 remains incomplete; Phase 3 has not started.

The [Phase 2 GitHub Actions run](https://github.com/dionis1/shipwise-ai/actions/runs/40867977754) on [`2846836`](https://github.com/dionis1/shipwise-ai/commit/28468365fe570927df77a669c77b60c7b6c102c1) completed successfully: backend, frontend and E2E all passed. Review is [PR #3](https://github.com/dionis1/shipwise-ai/pull/3), targeting main and left unmerged. Subsequent documentation commits run the same gates.
