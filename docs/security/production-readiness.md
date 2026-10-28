# Production readiness requirements

This repository is a development foundation, not a production release.

1. **Staff MFA is mandatory and missing.** Implement enrollment, challenge, recovery codes, audited reset and enforcement before allowing staff login in production. The code currently denies staff login for APP_ENV=prod. Do not bypass the guard to claim readiness.
2. Deploy immutable backend/frontend artifacts under TLS; replace development secrets and database defaults; configure trusted proxies and secure cookies. Keep Vite and Mailpit private.
3. Configure least-privilege runtime/migration database roles, append-only audit permissions, off-host backups, restore testing, audit retention and incident monitoring. Review account-enumeration timing and distributed session storage before scaling.
4. Introduce durable email outbox delivery/retries and verification resend/recovery workflows before high-volume onboarding. Clean expired tokens on a scheduled operational job.
5. Agree retention periods and legal bases with the controller: profiles/email are personal data; audit is access-restricted; Mailpit is development-only and contains live test tokens. Define export, correction, deletion/anonymization and retention exceptions. No legal GDPR compliance claim is made.
6. Perform a deployment-specific threat review, accessibility audit, dependency maintenance and penetration test. Register actual CI branch protections and security review owners.
7. Keep real carrier purchases, payment capture and AI actions disabled until contracts, PSP verification, idempotency, reconciliation, transactional outbox and private label storage have been implemented and tested.

Initial threat controls: credential theft → HttpOnly cookies/CSRF/hashing/rate limiting; privilege escalation → voters/role gates/last-admin locking; token theft → entropy/hash/expiry/single-use/fragments; data overexposure → response DTOs/allowlists/minimal support view; audit tampering → no ordinary mutation API and production privilege restrictions.
