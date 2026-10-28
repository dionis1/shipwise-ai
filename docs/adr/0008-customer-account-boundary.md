# ADR 0008: Customer accounts within Identity

Status: Accepted for Phase 1.

## Decision

Implement customer account/profile workflows in Identity rather than creating an empty CustomerAccounts module. Extract organizations, billing identity and memberships when real use cases require separate invariants.

## Consequences

Preserve explicit module boundaries, security tests and documentation when extending this decision. Production readiness must be assessed separately.
