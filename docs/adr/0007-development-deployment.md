# ADR 0007: Development deployment and production boundary

Status: Accepted for Phase 1.

## Decision

Use Docker Compose, Nginx, PHP-FPM, Vue/Vite, PostgreSQL, Redis and Mailpit locally. No RabbitMQ or paid services are needed in Phase 1. Redis Messenger is prepared; transactional outbox and dedicated broker decisions belong to real async workflows.

## Consequences

Preserve explicit module boundaries, security tests and documentation when extending this decision. Production readiness must be assessed separately.
