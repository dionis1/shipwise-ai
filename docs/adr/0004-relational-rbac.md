# ADR 0004: Relational permission-based authorization

Status: Accepted for Phase 1.

## Decision

Store roles and explicit permission joins in PostgreSQL. Keep platform roles separate from framework ROLE_STAFF and require Super Administrator membership for security-critical changes. System roles are protected.

## Consequences

Preserve explicit module boundaries, security tests and documentation when extending this decision. Production readiness must be assessed separately.
