# Phase 2 — Carrier comparison and quoting

Status: implementation in progress on `feat/phase-2-carrier-quoting`, based on verified main `6b9fe97`. Phase 1.5 remains an incomplete production gate. No Phase 3 purchase functionality is authorized.

## Scope and acceptance

Implement persisted carrier/service configuration, versioned development tariffs and pricing, canonical single-parcel requests, backend eligibility/pricing, immutable expiring offers, scoped guest/customer access, comparison and administration in EN/DE/FR. Preserve Phase 1 security and all existing tests. Completion requires fresh migrations, meaningful domain/API/browser tests, synchronized OpenAPI, static checks and green remote CI.

## Architecture

CarrierCatalog owns persisted carrier/service definitions and eligibility configuration. CarrierIntegration defines typed provider outcomes and a registry; the local deterministic tariff adapter is the only configured provider. Quoting validates requests, aggregates partial outcomes, applies pricing, persists immutable snapshots and enforces access/expiry. Pricing uses integer Money, basis-point margins and explicit versioned tax scenarios. Domain code has no framework dependencies; application ports separate SQL and provider infrastructure.

## Commercial boundaries

DHL, DPD, GLS and UPS records are disabled and unconfigured. No authorized credentials or commercial tariffs have been supplied. Development tariff amounts and delivery ranges are synthetic, clearly labeled and usable only outside production. Synthetic tax scenarios test the engine; they are not legal tax determinations or real payable offers. Undefined tax scenarios cannot finalize a price. No external HTTP adapter, payment, booking, shipment, label or tracking workflow is implemented.

## Planned validation

Test integer units/limits, postal rules for the supported DE/FR routes, unsupported routes, options and capabilities; money overflow/currency/rounding, pricing precedence and undefined tax; adapter failures/timeouts/partial success; database constraints/immutability/version activation; CSRF, guest token scope, ownership, idempotency, expiry, least-privilege administration and audit. Exercise comparison in EN/DE/FR and preserve regression workflows.

Detailed configuration, API, data model and actual execution evidence will be completed alongside implementation.
