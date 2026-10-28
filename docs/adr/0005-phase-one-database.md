# ADR 0005: Database ownership and mutation locking

Status: Accepted for Phase 1.

## Decision

Use ORM XML for accounts and DBAL for specialized relational repositories and read models. Serialize security mutations with a PostgreSQL transaction advisory lock. Avoid ORM schema updates across DBAL-owned tables.

## Consequences

Preserve explicit module boundaries, security tests and documentation when extending this decision. Production readiness must be assessed separately.
