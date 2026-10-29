# Phase 2 — Carrier comparison and quoting

Status: implementation complete, local verification in progress; remote CI evidence will be linked after execution. Based on verified main `6b9fe97`. Phase 1.5 remains an incomplete production gate. No Phase 3 purchase functionality is included.

## Working scope and architecture

- **CarrierCatalog:** persistent carriers and services, configured routes, size/weight/hand-over/speed capabilities, availability switches and immutable tariff versions. Carrier/service activation is backend-authorized and audited.
- **CarrierIntegration:** provider port, bounded registry, explicit success/failure outcomes and the deterministic development tariff provider. Responses are normalized and validated before pricing.
- **Quoting:** canonical single-parcel request, backend eligibility, aggregation, partial outcomes, scoped idempotency, persisted snapshots, owner/guest access, expiry and cleanup.
- **Pricing:** integer Money, service-specific/default rule precedence, margin minimums, fees and explicit versioned synthetic tax scenarios. Pricing administration has its own application port, SQL adapter and controller.

Domain models have no Symfony, Doctrine, HTTP or provider SDK dependency. Application ports isolate persistence and providers. SQL read projections combine catalog and active pricing data; controllers return the documented OpenAPI wire models. No new infrastructure service is required.

## Sources and commercial boundaries

`DEV_POST` is ShipWise's **development tariff source**, with `DEV_STANDARD` and `DEV_EXPRESS`. It is not DHL, DPD, GLS or UPS and is not a carrier sandbox. Seeded development tariffs cover DE→DE and DE→FR, EUR, one parcel. Standard supports drop-off up to 30 kg; Express supports drop-off/pickup up to 20 kg. Both use sorted side limits 1200×600×600 mm, allowing parcel rotation. Options are test capabilities, not commercial promises. Delivery estimates are deliberately null; fastest-delivery sorting is absent.

DHL, DPD, GLS and UPS are disabled, unconfigured catalog records with no services or rates. No carrier API or authorized commercial tariff was supplied or verified. Their enable requests are rejected. The development provider, tariff import and pricing changes refuse production use. Production does not generate development offers. The staff MFA restriction remains enforced.

All tariff amounts, margins and tax scenarios are synthetic test data. No legally verified commercial tax scenario exists. These quotes are not payable amounts, purchase commitments or guaranteed bookable services. Phase 2 does not add payments, orders, purchases, labels, tracking or fulfillment.

## Canonical validation and eligibility

The flat Shipment schema requires ISO country codes, bounded postal-code strings, integer grams and millimeters, quantity 1, handover (`any`, `drop_off`, `pickup`) and speed (`any`, `standard`, `express`). Central system bounds are 1–70000 g and 1–2000 mm per side in `ShipmentRequest`; persisted service rules impose lower limits. The UI accepts kilograms to three decimals and centimeters to one decimal, including a decimal comma, and converts decimal strings directly to integers without silent rounding.

DE/FR codes use five digits. This is format validation, not address existence or deliverability validation; it follows [La Poste's official addressing guidance](https://aide.laposte.fr/contenu/comment-saisir-correctement-une-destination) and the [UPU designated-operator directory](https://www.upu.int/en/Postal-Solutions/Programmes-Services/Addressing-Solutions?cid=127&csid=20). Other ISO codes have only bounded basic input validation, no invented country-specific regex; their routes currently produce ineligible outcomes. Full addresses/contact data are never requested.

Eligibility reasons distinguish disabled, unsupported route, excess weight/dimensions and unsupported handover/speed. Rating distinguishes unsupported service/capability, invalid parcel, unavailable provider, missing credentials, timeout, rate limit, temporary failure and invalid response. Missing active tariff/pricing produces `no_rate_source`; unknown tax produces `pricing_unavailable` with `tax_undefined`. One service failure does not suppress another offer.

## Pricing and provenance

All calculations use integer minor units. The local source is EUR only, with no FX conversion. Money supports explicit EUR/GBP/USD/CHF codes and rejects mismatches; this does not enable non-EUR tariffs. Amounts are bounded to prevent overflow.

1. Base tariff = configured base cents + ceiling(weight grams / 1000) × cents per started kg.
2. Purchase cost = base + configured carrier surcharge.
3. Margin = max(minimum margin, fixed margin + rounded purchase cost × margin basis points).
4. Net subtotal = purchase cost + margin + platform fee.
5. Synthetic tax = rounded net subtotal × explicit route tax basis points.
6. Total = net subtotal + synthetic tax.

Each percentage rounds half up with `intdiv(amount * basisPoints + 5000, 10000)`. A single active service-specific pricing rule supersedes the active default rule; they never stack. Unique partial indexes prevent conflicting active versions. Unknown route tax fails closed; no German VAT assumption is applied to real commerce. Synthetic scenario `synthetic-tax-1` uses 1900 basis points solely to test arithmetic.

Example test parcel: DE 10115 → FR 75001, 1001 g, 300×200×100 mm. Standard base 500 cents, surcharge 0, margin 150, fee 25, net 675, test tax 128, total **803 EUR cents**. Every offer stores tariff version, pricing version, tax scenario/rate and full calculation breakdown. Reconfiguration affects only newly created quotes.

## Persistence, access and retention

Additive migrations introduce carriers, carrier_services, carrier_tariffs, pricing_rules, shipping_quotes and shipping_quote_offers. Existing identity/RBAC tables are preserved; only new permission grants are added. Quote/offer IDs are UUIDs. Quotes reference their account owner; offers reference their quote. UTC creation, expiry and purge timestamps are explicit. Offers have BIGINT minor-unit totals and separate currency columns checked against their immutable JSON pricing snapshots. Database triggers reject quote/offer updates and historical tariff/pricing configuration changes. Active-version flags may change; historical versions cannot be edited through the API.

Offers last **15 minutes**. The offer-inspection endpoint returns 410 after expiry; old offers are never repriced. All preliminary quote records have a **24-hour** retention window, including authenticated quotes. There is no durable account quote-history UI in this phase. Reads and idempotency replay fail after the retention deadline even before cleanup. Run `php bin/console app:purge-quotes` at least hourly in any deployed quotation environment; it deletes expired retention windows and cascades their offers without logging shipment data. Scheduling/operations remain a deployment obligation.

Guest creation uses an unguessable session scope and a quote-specific HMAC token; only its hash is stored in PostgreSQL. The browser holds the token in component memory, never in URLs or localStorage. Guest reads require both token and original session. Logged-in quote reads require the exact owner; staff roles do not grant blanket private-quote access. Login/logout does not convert or expose a guest quote to another owner.

`Idempotency-Key` is required (16–80 alphanumeric/hyphen characters). Its hash is scoped to owner/session. PostgreSQL advisory locks and a unique constraint serialize concurrent duplicates. The same canonical body returns the original snapshot and original expiry; a changed body returns 409. Scope/body hashes and guest token hashes never appear in API responses.

## API and UX

All paths below are under `/api/v1`; OpenAPI uses the existing `/api/v1` server base.

| Method/path | Behavior |
| --- | --- |
| GET `/carriers` | Public source availability, display countries and canonical units |
| POST `/quotes` | CSRF-protected, rate-limited validation, calculation and snapshot creation |
| GET `/quotes/{id}` | Owner or session-scoped guest-token read |
| GET `/quotes/{id}/offers/{offerId}` | Access/expiry validation and offer summary; never books |
| GET `/admin/carriers` | Catalog/service availability without confidential tariff/pricing configurations |
| PATCH `/admin/carriers/{code}` | Confirmed carrier/service availability switch |
| GET/POST `/admin/tariffs` | Inspect versions / activate a new development tariff version |
| GET/POST `/admin/pricing-rules` | Inspect / activate versioned synthetic pricing rules |
| GET `/admin/quote-diagnostics` | Aggregate status counts from the last 24 hours; no private shipment data |

New permissions: `carriers.catalog.read`, `carriers.configure`, `tariffs.manage`, `quotes.diagnostics` for SUPER_ADMIN and CARRIER_MANAGER; `pricing.manage` only for SUPER_ADMIN. Existing Finance, Marketing, Support and QA grants are unchanged. All decisions use backend voters. Mutations and their sanitized audit events share transactions.

`/compare` reuses public/account navigation, light cards, inputs, selects, badges, feedback and native drawers. Users enter details, compare backend-priced cards, sort by price or carrier/service, inspect the immutable breakdown and re-search after expiry. No enabled Pay/Buy Label control exists. `/admin/carriers` provides real availability switches, tariff imports, pricing creation, version inspection and aggregate diagnostics. Sensitive switches use confirmation dialogs. Versions are append-only.

EN/DE/FR resources cover all new labels, validation, result states, reasons and accessible names. Locale-aware Intl formatting displays amounts and UTC dates. Translation checks verify matching keys/placeholders, untranslated Vue copy and unknown static translation keys. Dynamic provider/service codes remain language-neutral and require resource additions when integrating another service.

## Configuration and extension guide

For the reproducible local environment: run `make migrate`, keep APP_ENV=dev, open `/compare`, and use the example parcel above. No external credentials are needed. Migrations seed development tariffs; normal users cannot edit them. A local Super Administrator or Carrier Manager can import a **new unique development tariff version** in `/admin/carriers`. Set service, EUR base/per-started-kg/surcharge integer cents and an effective UTC timestamp at or before now. New activation retires the previous active flag; duplicate versions and future activation dates are rejected.

Only a Super Administrator can create a new pricing version. Choose a default or service-specific scope, integer margin/fee values and a synthetic test-tax basis-point value. Review the form before saving; this is not a legal tax configuration tool. Historical values remain inspectable.

**Authorized contract imports and sandbox credentials are blocked pending actual access.** No placeholder credentials or fabricated provider endpoint is consumed. Before adding one: obtain commercial authorization, confirm the documented rating capability/endpoint and permitted tariff storage, approve supported routes/limits/options and tax scenarios, then implement a dedicated `CarrierRateProvider` and import validator with provenance. Extend the catalog mode constraint deliberately, inject secrets through environment/managed-secret configuration, register the adapter and cover sanitized documented responses. Do not put secrets or contract files in ordinary database rows, frontend code, logs or Git.

Registry size is capped at five adapters and requests are processed sequentially (concurrency one), with at most 20 configured services per quote. The current adapter performs bounded in-memory arithmetic, no external requests and no retries. A future HTTP adapter must use an approved fixed endpoint, verified TLS, a connect timeout of at most 2 seconds and a total request timeout of at most 4 seconds, bounded response size and explicit timeout/rate-limit classification before it can be enabled. No customer-provided URL fetcher exists.

Debug with the response `X-Request-ID`, sanitized audit entries and authorized aggregate diagnostics. An invalid provider response yields an explicit failure, never an invented price. Do not log credentials, quote-access headers, full provider payloads or shipment details. Run `make backend-test`, `make test`, `make quality` and `make e2e`; no live provider account is required for CI.

## Acceptance and limitations

Required acceptance covers canonical validation, eligibility boundaries, source provenance, integer pricing/rounding, rule/version precedence, immutable persistence, expiry, ownership/guest isolation, idempotency, least-privilege administration/audit, translations, accessible responsive comparison, fresh migrations, contracts, dependency checks and complete existing regressions. Actual counts and CI links belong in `docs/verification.md`.

Remaining limitations: one parcel, two synthetic development services/routes, EUR source, no real contract/sandbox/live provider, no verified commercial tax treatment, no deliverability lookup, no delivery estimates, no durable account history, no booking. Automated Axe/browser scans are not a comprehensive accessibility audit. Cleanup scheduling, production staff MFA and all other Phase 1.5 deployment gates remain outstanding. Phase 3 requires a separate implementation request and verified provider purchase/idempotency/payment designs; it has not started.
