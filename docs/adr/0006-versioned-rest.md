# ADR 0006: Separate Vue client and versioned REST contract

Status: Accepted for Phase 1.

## Decision

Keep Vue and Symfony behind one same-origin reverse proxy, communicate through /api/v1 JSON and generate TypeScript models from checked-in OpenAPI. This preserves independent builds without cross-origin cookie complexity.

## Consequences

Preserve explicit module boundaries, security tests and documentation when extending this decision. Production readiness must be assessed separately.
