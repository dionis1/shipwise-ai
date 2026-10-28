# ADR 0002: Payment-confirmed, asynchronous carrier purchase

- **Status:** Accepted for prototype, subject to PSP and carrier agreements
- **Context:** A paid customer order and a carrier's label creation cannot be committed in one atomic transaction. Retries can trigger duplicate purchases, and successful external calls may time out before a response is received.
- **Decision:** Validate an immutable quote and create an idempotent order. Confirm payment via verified PSP webhook. Commit payment state plus outbox event in one PostgreSQL transaction. A Symfony Messenger worker coordinates carrier booking and private label storage. Failure leads to compensating refund; ambiguous outcomes require reconciliation before retries.
- **Safety controls:** Stable idempotency keys, unique provider external references, persistent webhook inbox, outbox relay, typed provider errors, failure queue, manual remediation, audit trail, observability.
- **Consequences:** The user interface must show an intermediate processing state; booking is not always immediate. Financial reconciliation is a first-class feature.
- **Alternative:** Authorize then capture after label creation; revisit once PSP holds and provider billing exposure have been validated.
