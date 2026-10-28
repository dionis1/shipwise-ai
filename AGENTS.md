# Engineering rules
Source code, identifiers, comments, documentation, API contracts, tests and commit messages must remain English. User-facing copy must use English, German and French translation resources. German and French are permitted in translation files and localized email templates only. Do not hardcode user-facing copy in Vue components.
Preserve the modular monolith and dependency direction: domain → application ports ← infrastructure; presentation translates HTTP. Domain rules must not depend on Symfony, Doctrine or HTTP.
Enforce permissions on the backend; navigation is never an authorization boundary. Preserve single-use tokens, session revocation, CSRF, audit atomicity and last-administrator protection.
Run relevant tests and update the OpenAPI contract, generated types and documentation with behavior changes. Never claim unexecuted checks passed.
Do not introduce real carrier purchases, payments or AI recommendations in Phase 1. Never fabricate business metrics. Do not commit secrets or ZIP archives.

## Git workflow and commit standards
Review requirements, implement one coherent unit with tests and documentation, run relevant checks, inspect the diff, stage explicit paths and create an English Conventional Commit before continuing. Never blindly use `git add .`.
Never combine a whole phase into one commit. Use small, atomic commits with descriptive `feat`, `fix`, `refactor`, `test`, `docs`, `build`, `ci` or `chore` messages. Keep tests with their functionality where practical. Generic messages such as “update”, “changes”, “work” and “final version” are prohibited.
Never commit secrets, credentials, local environment files or build artifacts. Do not rewrite published history without explicit authorization. Do not fabricate tests or history. Incomplete intermediate commits require explicit documentation and a development branch; never knowingly commit broken functionality as complete.
Use descriptive feature branches and focused pull requests where supported. PRs describe what changed, why, verification and known limitations. Preserve readable portfolio-quality history.

## Phase-specific release requirements
Before working on production deployment, staff MFA, account email delivery, backup/restore, security monitoring or release hardening, read `docs/phases/PHASE_1_5_PRODUCTION_READINESS.md` and the existing `docs/security/production-readiness.md`. The Phase 1.5 document is a **specification, not completed implementation**. Keep the production staff-login guard in place until an enforced MFA flow is implemented and verified; never disable it to make deployment tests pass. Preserve the distinct scope boundary: carrier quotes and purchasing remain later-phase features. Update acceptance evidence only after actual tests and staging deployment checks, and commit release changes in small, coherent English Conventional Commits.
