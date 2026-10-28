# Phase 1.5 — Production Readiness for the Account and Administration Foundation

**Project:** ShipWise AI  
**Status:** Planned — requirements only; not implemented by creating this document  
**Prerequisite:** Phase 1 implemented and its backend, frontend and E2E CI quality gates passing  
**Target:** Safely operate the existing customer identity, account, staff administration, role-based access control (RBAC), audit and API capabilities in a production environment  
**Source baseline:** `docs/phase-1-requirements.md`, `docs/security/production-readiness.md`, `docs/architecture/`, and `docs/verification.md`

## 1. Mission, boundaries, and release policy

Turn the existing Phase 1 **development foundation** into a production-deployable, supportable, and security-reviewed foundation. Work on the current Symfony 7.4 / PHP 8.4, Vue 3 / TypeScript, PostgreSQL, Redis and Docker architecture. Preserve its modular monolith, English-only codebase, typed API contracts, permission boundaries and audit model.

**In scope:** Customer registration, email verification, login/logout, password reset, profile management, staff invitations, employee login, MFA, role and permission administration, department dashboard shells, audited staff actions, secure deployment, monitoring, backups, operational controls, CI/CD and privacy-related customer-account workflows.

**Out of scope:** Actual DHL/DPD/GLS/UPS integrations, carrier contracts, real quotes, shipping price calculations, payment collection, orders, fulfillment, tracking, label storage, and AI recommendations. Those belong to later product phases. No fabricated financial or shipping activity.

This phase is a **production release gate**, not permission to bypass security controls. Existing staff accounts are deliberately rejected when `APP_ENV=prod` by `AccountChecker` / `AccountProvider`; do not remove that guard until the complete mandatory staff-MFA login flow is implemented, enforced and tested in production configuration. Do not assert production readiness based solely on successful CI or a local Docker startup.

All code, identifiers, documentation, UI text, tests, Git commits and pull requests must be **in English**. Changes must be split into coherent, reviewable Conventional Commits and focused pull requests.

## 2. Verified starting point and current blockers

Already implemented: server-side RBAC, customer onboarding, single-use hashed tokens, cookie sessions, CSRF, rate limiting, permission-aware staff UI, audit records, API contracts and local Docker development. The remote Phase 1 GitHub Actions checks have passed for backend, frontend and E2E as documented in `docs/verification.md`.

**Not yet established:** production container images and TLS configuration; staff MFA; real durable email delivery; production secrets management; DB privilege isolation, backups and restore drills; monitoring/alerting; approved retention and data-subject workflows; a deployment-specific threat/security review; protected release branches and verified staging-to-production delivery.

Every task below requires real implementation and evidence. A checkbox must not be marked done based on a plan, mock integration, unexecuted test or documentation claim.

## 3. Workstream PR-01: Hardened production runtime and configuration

**Requirements**
- Create a reproducible **production-only** container/deployment configuration separate from `compose.yaml` (which currently runs `APP_ENV=dev`, mounts source files, uses Vite and Mailpit, and contains local placeholder secrets).
- Build Vue into immutable static assets using `npm ci` and a production build; serve through a hardened web server. **Never** expose a Vite dev server in production.
- Build a PHP runtime artifact with locked and production-appropriate Composer dependencies, Symfony cache warmup, no development tooling exposed, and non-root runtime process where feasible. Use explicit image versioning and a deployment rollback strategy.
- Serve frontend and `/api/v1` on an approved **HTTPS same-origin** domain. Define TLS termination, trusted proxies/hosts, redirect from HTTP, appropriate HSTS/security headers, content security policy, body limits and restrictive cross-origin policy.
- Ensure secure cookie/session settings, CSRF validation, session renewal and expiry, transport security, request limits, production-safe error pages, and no debug or internal stack traces.
- Move `APP_SECRET`, PostgreSQL credentials, SMTP credentials and other secrets into a secret manager/secure deployment injection. Never commit credentials or use development defaults for production.
- Keep PostgreSQL, Redis, worker queues, administration interfaces and email testing tools on private networks; no exposed database/Redis ports, and **no Mailpit** in production.
- Provide independent liveness/readiness checks that do not disclose sensitive operational details. Record the expected topology and deployment procedure.
- Support a **single-instance production deployment** first; if multiple application instances are used, configure centralized sessions, shared locks and rate-limiter persistence, and verify horizontal behavior.
- Separate production configuration from local and E2E configuration. Fail closed on missing required secrets and unsafe runtime defaults.

**Acceptance evidence:** A clean tagged build deploys to a production-like staging environment via HTTPS; no Vite/Mailpit/dev credentials are reachable; cookies and headers are inspected; authenticated customer/staff routes and the API operate behind the proxy; configuration is reproducible without manually editing containers.

## 4. Workstream PR-02: Mandatory staff multi-factor authentication

**Requirements**
- Introduce a clear, testable authentication state machine: password accepted -> MFA challenge (or enrollment for authorized first use) -> fully authenticated session. A password alone must never create an authorized staff session in production.
- Implement standards-based TOTP using an authenticator app, with secret enrollment confirmation; securely protect TOTP seeds at rest using managed encryption keys, and never log or expose seeds after enrollment.
- Generate high-entropy, **single-use, hashed** recovery codes with a one-time display; enforce rate limits on MFA challenge and recovery use.
- Provide administrator-controlled, auditable recovery/reset with identity verification and safeguards against administrator impersonation or privilege escalation. Revoke affected sessions on reset.
- Prevent MFA bypass via JSON login responses, cookie reuse, password resets, invitation acceptance, suspended users, account-type switching, API endpoints, and refresh of existing sessions.
- Require a fresh MFA challenge or suitable reauthentication for sensitive role/permission and MFA-management operations as appropriate.
- Preserve the existing `APP_ENV=prod` staff-login denial until the new production-authentication flow is proven to enforce MFA. Replace it with an **enforced MFA gate**, never a weaker password-only fallback.
- Preserve customer authentication and the last-active-Super-Administrator safeguard.

**Acceptance evidence:** Tests show that staff cannot reach `/api/v1/admin/**` or admin views after password-only login; valid TOTP or an unused recovery code permits authorization; invalid/replayed/reused codes fail; reset and role changes revoke sessions; rate limits work; product owners sign off on emergency access.

## 5. Workstream PR-03: Durable account email and recovery

**Requirements**
- Replace local Mailpit-only delivery with an approved real transactional mail provider for staging/production. Configure SPF, DKIM and DMARC for the sending domain, bounce monitoring and production-safe sender identity.
- Implement a transactional **email outbox**: persist messages/events in the same DB transaction as state changes; dispatch with Messenger workers; retry transient failures with bounded backoff, observability and dead-letter recovery. Treat delivery as at-least-once and make handling idempotent; never claim guaranteed exactly-once email delivery.
- Keep verification, reset and staff invitation tokens single-use, expiring, hashed in storage, and absent from ordinary logs.
- Implement verification resend, invitation reissue, password-reset request recovery and delivery-failure handling with enumeration-resistant public responses and per-account/IP throttling.
- Schedule expiration/cleanup of obsolete tokens, expired invitations and outbox entries with reviewed retention policy.
- Ensure a DB rollback cannot leave a just-sent verification link pointing at nonexistent committed state.

**Acceptance evidence:** In staging, real email links work; mailbox delivery outages do not lose committed account events; retries and duplicate events are safe; expired/reused tokens fail; resend and bounce paths are covered by tests.

## 6. Workstream PR-04: Database, backup, audit integrity and recovery

**Requirements**
- Separate migration/administration DB credentials from least-privileged application credentials. Use TLS for DB connections wherever required by infrastructure.
- Enforce appropriate constraints/indexes and minimize access to credentials, tokens, account profiles and audit records.
- Protect audit storage against updates/deletions by ordinary application DB credentials; define immutable/off-host export and operational retention strategy. Keep actor, correlation ID, timestamp and redacted context.
- Implement encrypted, scheduled, off-host database backups and document backup retention and access restrictions.
- Perform an actual **restore drill** into an isolated environment. Define project-specific recovery point objective (RPO) and recovery time objective (RTO) with stakeholders; do not invent SLAs.
- Document migration rollback/forward recovery and backup-before-migration procedures, with zero destructive production `make reset` execution.
- Verify session storage and Redis recovery expectations under restart/failure scenarios.

**Acceptance evidence:** A stored backup is restored, data integrity is validated, least-privilege access tests pass, runtime users cannot alter audit history, and recovery exercise notes are recorded.

## 7. Workstream PR-05: Observability, security operations and resilience

**Requirements**
- Produce structured, centrally collected logs with correlation IDs; redact passwords, access credentials, reset/verification tokens, TOTP secrets, sensitive customer data and SMTP secrets.
- Add non-sensitive operational metrics for API availability, request failures, authentication/MFA failures, rate-limit rejections, mail outbox lag/failures and worker health.
- Configure actionable alerts and ownership for infrastructure downtime, repeated privileged login failures, worker failures, failed database backups and suspicious permission changes.
- Implement sensible application/server request timeouts, resource limits, health check dependencies and controlled restarts.
- Document incident response, key rotation, compromised-account response, emergency access and on-call ownership (even if the same person performs all roles initially).
- Review authentication abuse protection: per-account/IP rules, signup/reset abuse, generic public responses, lockout/recovery behavior, and trusted proxy IP handling.

**Acceptance evidence:** Staging exercises trigger alerts, show request traces without leaking secrets, verify worker retry/dead-letter visibility, and demonstrate incident runbooks.

## 8. Workstream PR-06: Privacy, account lifecycle and security validation

**Requirements**
- Inventory personal data in customer/staff profiles, logs, tokens, audit events, email provider and backups. Establish retention, legal basis, roles/access and a privacy notice with qualified legal review for applicable jurisdictions.
- Define and implement or operationally support authenticated personal-data access/export, correction and deletion/anonymization requests with appropriate identity verification, legal retention exceptions and audit requirements.
- Define how customer account closure interacts with future orders/payments/shipments, without introducing those later-phase entities now.
- Ensure support and marketing roles receive only approved minimal data; keep finance and marketing segregated.
- Complete an application threat model covering login, MFA reset, CSRF, session fixation, broken access control/IDOR, token replay, rate limiting, injection, XSS, sensitive logs and dependency risks.
- Run independent or appropriately scoped staging security tests (including OWASP ASVS-oriented checks), negative authorization tests and an accessibility review (keyboard, forms, navigation, error states). Record findings and remediate high/critical defects.
- Obtain legal/privacy sign-off where required; do not declare GDPR compliance purely from code or tests.

**Acceptance evidence:** Documented data-handling rules and tested account-rights workflows, no open critical/high security findings at release, and a traceable privacy/accessibility review record.

## 9. Workstream PR-07: CI/CD, release governance and go-live

**Requirements**
- Establish a stable protected `main` branch with feature branches and reviewable pull requests. Require passing backend/frontend/E2E CI checks, reviewed changes and resolved critical conversations where hosting plan/repository settings permit.
- Build and publish versioned, immutable container artifacts after successful quality gates; scan dependencies/images and document provenance, artifact retention and release tagging.
- Use separate staging and production environments, protected deployment credentials and an explicit promotion/approval process. No development secrets or demo fixtures in production.
- Deploy with reproducible steps: backup -> migration -> deploy -> health checks -> authenticated smoke tests -> traffic release. Document rollback/forward-fix actions and responsible approvers.
- Run smoke tests for customer registration/verification, login, password reset, profile update, staff invitation, MFA, RBAC denials, admin navigation, and audit history against staging. Do not put real secrets in test reports.
- Capture final artifacts and test outcomes in `docs/verification.md` and production deployment runbooks. Report actual run URLs/SHAs and known residual risks; do not fabricate passing runs.

**Acceptance evidence:** A staging release candidate is deployable repeatedly, CI is green, approval and rollback paths are exercised, and a separate release checklist is signed off before opening the service to real users.

## 10. Engineering implementation order and Git standards

Implement as small vertical slices, not one enormous change or one commit per file. Suggested delivery order:
1. Deployable production-like staging runtime, TLS and configuration.
2. Staff MFA enrollment, challenge, recovery and authorization tests.
3. Durable email outbox, provider integration, worker operations and resend flows.
4. Database role separation, audit integrity, backups and tested restores.
5. Monitoring, alerts, abuse protection and incident runbooks.
6. Privacy/account-rights workflows, accessibility and security review.
7. Protected release branch, CI/CD promotion, staging smoke tests and release sign-off.

Create relevant ADRs when materially changing authentication, deployment, data retention or event delivery. Maintain OpenAPI and generated TypeScript models whenever changing endpoints. Write English Conventional Commits, tests with changes and focused pull requests. Preserve existing working Phase 1 behaviors. Never commit demo credentials or production secrets.

## 11. Definition of Done — production release gate

Phase 1.5 may be marked **complete** only if all of the following are verified:
- [ ] Production-grade, immutable Symfony and Vue artifacts run behind HTTPS with secure headers, cookies, proxies and no dev servers/debug endpoints.
- [ ] Production secrets are managed outside Git; development default secrets and passwords cannot be used in production.
- [ ] Staff MFA enrollment, login challenge, recovery and audited reset are implemented; there is **no** password-only staff access path.
- [ ] The former production staff-login guard is safely replaced only after MFA enforcement tests pass; customer auth still works.
- [ ] All Phase 1 backend permissions remain server-enforced and tested for cross-role/customer access and privilege escalation.
- [ ] Verified email delivery uses a durable outbox/worker, with retries, resend/recovery and token cleanup.
- [ ] Runtime DB credentials use least privilege and cannot update/delete ordinary audit history.
- [ ] Encrypted off-host backups exist and a documented restore drill has passed.
- [ ] Structured logging, metrics, production alerts and incident response runbooks exist without leaking credentials or PII.
- [ ] Data handling/retention, account data access/deletion processes and relevant legal review are documented and exercised.
- [ ] Security and accessibility reviews are completed and all release-blocking findings fixed.
- [ ] A protected release workflow, versioned artifacts, staging deployment and a tested recovery/rollback process exist.
- [ ] The final GitHub CI and staging authenticated smoke tests pass, with links to actual execution evidence.
- [ ] Production release owner explicitly approves launch; residual risks and operational owners are recorded.
- [ ] Shipping quotations, carrier purchases, payments, labels and AI actions remain disabled and are not represented as live features.

## 12. Handoff to Phase 2

After this production gate, Phase 2 can add a **carrier catalog, shipment-eligibility checks, quote aggregation and transparent backend pricing** behind the existing identity/RBAC/API foundation. Start with one contract-authorized **sandbox or test carrier adapter** and a clearly provenance-tagged tariff source; only then expand to additional providers. Keep live purchases, payment capture, order fulfillment, tracking and AI recommendations outside Phase 2 unless a separate future-phase decision explicitly changes scope.

A quote is not proof of a bookable carrier service. Do not advertise a live price or resale permission without validating the provider API capability, service agreement, exclusions, rate source and tax treatment.
