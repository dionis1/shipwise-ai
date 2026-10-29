# Database

PostgreSQL 17 is the source of truth; the Docker volume persists development data. `make migrate` applies the initial `Version20261028000100.php` and additive `Version20261028000200.php` migrations. The latter adds `users.preferred_locale`, defaulting existing accounts to English and enforcing en/de/fr with a CHECK constraint. No automatic schema update runs at startup.

Tables: users (UUID string, normalized unique email, name, hash, account kind/status, session version, preferred locale, UTC creation time); roles and permissions (stable codes); user_roles and role_permissions (composite PKs/FKs); staff_invitations, email_verification_tokens, password_reset_tokens (unique token hash and user FK, expiry); audit_events (UUID, nullable actor FK, action, target ID, correlation ID, JSONB sanitized context, UTC time). Status and kind use database CHECK constraints. FK and query indexes support role evaluation, expiry and audit timelines.

Audit target IDs intentionally remain historical identifiers; actor FKs allow SET NULL for eventual anonymization. Ordinary APIs expose no audit update/delete operation. Production database roles must restrict write privileges and establish retention/export controls.

DBAL tables are deliberately outside ORM metadata. Use `doctrine:schema:validate --skip-sync` for ORM mapping and run the real migration on an empty PostgreSQL database to validate the full schema. Do not use ORM schema:update; it would propose removing DBAL-owned tables.

Future organization memberships, authorized carrier accounts, orders, payments and shipments remain documented designs only. Preliminary development quotations are implemented in Phase 2.

Phase 2 adds versioned carrier/service and tariff configuration, scoped pricing versions, owner/guest quotation records and immutable offer snapshots through `Version20261029000100` and `Version20261029000200`. Offer totals use constrained BIGINT minor units and separate currencies matched to the JSON snapshot. Foreign keys, active-version unique indexes, update triggers, expiry/retention indexes and scope/idempotency uniqueness protect consistency. Run `app:purge-quotes` at least hourly. See the Phase 2 guide for the 24-hour preliminary retention policy.
