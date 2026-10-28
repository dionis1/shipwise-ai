# Backend

PHP 8.4 and Symfony 7.4 provide routing, DI, security, validators, mail, rate limiting and Messenger. Doctrine ORM maps the framework-independent User through XML. PostgreSQL/DBAL handles tokens, role joins, audit and read models. Separate repository ports avoid HTTP-bound business rules.

Account changes are coordinated through Transactions; the PostgreSQL advisory transaction lock serializes security mutations so two administrators cannot concurrently remove the final active Super Administrator. Tokens are consumed by atomic DELETE RETURNING within the same transaction as the account change. Exceptions roll back both operations. One coarse security lock intentionally favors correctness over high mutation throughput in Phase 1. Read traffic is not locked.

Synchronous transactional email is used locally. Delivery occurs before commit, so an SMTP failure rolls back changes. A commit failure after successful SMTP delivery can yield an unusable link; a durable mail outbox is a production follow-up. Redis Messenger transports are configured for future async jobs; no fake worker workflow is claimed.
