# ADR 0003: Same-origin session authentication

Status: Accepted for Phase 1.

## Decision

Use Symfony session cookies and CSRF instead of browser bearer-token storage. Session version checks revoke access after sensitive account changes. This requires shared session storage before scaling horizontally.

## Consequences

Preserve explicit module boundaries, security tests and documentation when extending this decision. Production readiness must be assessed separately.
