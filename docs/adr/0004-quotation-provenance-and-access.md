# ADR 0004 — Immutable quotations with explicit development provenance

Accepted for Phase 2. Carrier contracts and credentials are unavailable, so the initial provider is a deterministic development tariff source with its own identity. Real carrier records stay disabled/unconfigured; capability claims are limited to configured development services. Production refuses development rates.

Use canonical integer grams/millimeters and integer minor-unit money. A single service-specific pricing rule supersedes the default; rules never stack. Round each basis-point calculation half up using integer arithmetic. The explicit versioned tax scenario applies to net purchase cost plus surcharges, margin and fees. Undefined scenarios fail closed. Synthetic tax is for development validation only.

Persist offer JSON snapshots, source/version and UTC expiry in PostgreSQL; enforce immutability with database triggers. Reconfiguration affects new quotes only. Customer reads require ownership. Guest reads require an unguessable quote-scoped token and the originating session scope. Tokens stay in browser memory and are not placed in URLs, logs or localStorage. Guest quotes are retained at most 24 hours and cleaned by an explicit maintenance command. Quote validity is 15 minutes. Idempotency is scoped to the authenticated account or guest session and rejects a changed request body; it never refreshes an expired offer.

Provider calls are sequential (concurrency one), registry size bounded, no retries for local deterministic operations. Future external adapters must implement approved fixed endpoints, TLS validation, total/per-request timeouts, normalized bounded responses and explicit failure classifications before registration. No configurable customer URL fetcher is provided.
