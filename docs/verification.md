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
