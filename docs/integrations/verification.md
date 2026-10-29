# Carrier sandbox verification — 2026-10-29

Implementation base: verified `main` at `674340258ec4e4a7b177aeffb110e7492012907e` ([successful base CI](https://github.com/dionis1/shipwise-ai/actions/runs/40870747619)). Work branch: `feat/carrier-sandbox-integrations`. [Draft pull request #5](https://github.com/dionis1/shipwise-ai/pull/5) targets `main`; no merge was performed. The draft intentionally retains the missing GLS contract as an unresolved requirement.

## Capability and access evidence

| Provider | Implemented | Mock-tested | Authenticated sandbox-tested | Provider-approved | Production-ready |
| --- | --- | --- | --- | --- | --- |
| DHL | Password-grant authentication, version/hello check, fixed synthetic shipment validation with `validate=true` | Yes | No | Not evidenced | No |
| DPD | eSolutions Login 2.0, SOAP Parcel Shop Finder 5.0, sparse normalized location mapping | Yes | No | Not evidenced | No |
| GLS | No adapter: official contract unavailable without portal access | No | No | Not evidenced | No |
| UPS | No adapter; catalog remains disabled/unconfigured | Not applicable | No | Not evidenced | No |

All integrations are disabled by default. These adapters are not rate providers. Existing development tariffs and immutable quote calculations are preserved. No shipment creation, booking, postage purchases, labels, payments, tracking or Phase 3 work is included.

### External operations actually performed

Only public documentation/metadata was retrieved: DHL Shipping and Authentication OpenAPI documents, the DPD Login 2.0 / Finder 5.0 sandbox WSDL and Finder XSD, and the public GLS portal. This demonstrates contract availability, not authenticated carrier connectivity. The GLS API catalog displayed “Sign in to view APIs”.

**No authenticated sandbox API call was executed.** No real shipment validation or location lookup was performed. Runtime statuses remain unconfigured/disabled, with GLS reporting `specification_unavailable`. Mock responses exist only in tests and Playwright interception, never as fallback runtime locations or rates.

### Access still required

- DHL: dedicated sandbox client credentials, sandbox test-user credentials; a sandbox billing number/profile for synthetic fixture validation. Authenticated hello success does not establish sales authorization.
- DPD: dedicated eSolutions sandbox DELIS credentials and provider access to Parcel Shop Finder. Login success alone does not prove finder entitlement. DPD Cloud REST credentials cannot substitute.
- GLS: authorized official Authentication API v2 and OOH Location Finder by Address (single) OpenAPI/Swagger specification or an authenticated official portal session. The implementation must wait for this contract. Afterward, separate provider entitlement, secure credentials and an explicit sandbox smoke test are still required. Protected Drop-off/Pick-up and parcel-order APIs remain excluded.
- Production access and commercial carrier rating approval remain absent for every carrier. UPS stays untouched.

Do not provide secrets in chat or source. Use ignored `apps/backend/.env.local` or managed runtime secrets. Enable a development-only provider environment flag, then an audited administrative switch; invoke a specific check explicitly. Test, E2E and production environments cannot activate external networking through these flags.

## API and interface changes

- `GET /api/v1/parcel-shops`: validated DE/FR country, postal code, optional city, carrier filter and bounded limit; normalized nullable location fields, supplied service codes, retrieval timestamp and sandbox source. Empty, unavailable and provider rejection outcomes are distinct. Searches/results are not persisted; normal access logging is disabled for this route; IP rate limiting and `no-store` apply.
- `GET /api/v1/admin/carrier-sandboxes`: actual configuration, implementation capabilities, operation-specific evidence and timestamps. Evidence expires after ten minutes and is invalidated by configuration changes.
- `PATCH /api/v1/admin/carrier-sandboxes/{code}`: audited sandbox switch, independent of catalog shipping enablement. No secret-writing API exists.
- `POST /api/v1/admin/carrier-sandboxes/{code}/check`: explicit authentication check or DHL-only fixed-fixture validation. Existing backend permissions, session, CSRF and actor rate limiting are enforced. Audit records contain stable statuses only.
- `/parcel-shops`: responsive accessible EN/DE/FR search and honest unavailable/error/empty/result states. No map or fabricated locations/prices. `/admin/carriers` adds guarded sandbox diagnostics. `/compare` is preserved.
- OpenAPI and generated TypeScript cover all 41 API operations.

## Executed verification

| Check | Actual local result |
| --- | --- |
| Full PHPUnit suite | **123 tests, 417 assertions passed** |
| Vitest | **57 tests in 11 files passed** |
| Playwright | **29 tests passed**, including all prior workflows |
| Discovery UI | EN/DE/FR, 1440/768/390 px, unavailable and intercepted synthetic results, empty/error states, WCAG-tagged axe checks |
| Translation checker | **501 keys per locale**, matching placeholders, localized Vue copy |
| API route parity | **41 operations**, Symfony/OpenAPI synchronized |
| Backend quality | Composer strict validation, PHPStan level 6, uncached PHP-CS-Fixer, service/YAML/Doctrine mapping validation passed |
| Frontend quality | TypeScript, ESLint, Prettier, production build and OpenAPI lint passed |
| Dependency checks | npm audit: zero vulnerabilities; Composer audit completed with the existing scoped `PKSA-wtxr-p26d-nn42` metadata exception, reevaluation date 2026-11-04; no new exemption added |
| Infrastructure | Nginx syntax validated/reloaded; isolated E2E containers/volumes removed; normal six ShipWise services retained |

Focused negative tests cover token expiry/cache invalidation, refresh contention, incorrect credentials, one-time reauthentication, 401/403/429/503, timeouts, oversized and malformed responses, XXE/DTD rejection, invalid search fields, missing configuration/GLS specification, CSRF/RBAC and rate limits. The complete mocked DPD response is also exercised through the public API, including nullable fields and absence of search retention. The first browser run exposed an incorrect test selector and was stopped; the corrected full rerun passed all 29 tests. Failed attempts are not counted as passes.

CI results must be read from the [current pull request checks](https://github.com/dionis1/shipwise-ai/pull/5/checks); local verification is not a substitute for CI. No authenticated smoke-test success or approval is inferred from CI mocks.

## Independently reviewable implementation commits

- `9fdbc6815642bf062f46e9179231890e8d697fa5` — feat(integrations): isolate sandbox capabilities and secure transport
- `fbcbf91c36710215d61841e73adfc2b516e77a47` — feat(dhl): add sandbox authentication and validation-only client
- `b5c03b553a2c4a43377d1a6d1f85080c7033b46a` — feat(dpd): add sandbox login and parcel shop finder adapters
- `cf0739e22105bfa06bd9a5b2867c92f8ab15cc43` — feat(integrations): expose rate-limited discovery and audited sandbox controls
- `16cf58c0891d2c756a716d1f543447d94c1022dc` — feat(ui): add multilingual parcel shop discovery and sandbox diagnostics
- `554bcb20d4fd47457935403d8734d56c8cbce7f6` — fix(integrations): bound refresh contention and sanitize transport diagnostics
- `3aaa16ebcec687b377144870bb8aea7e924ab355` — fix(dhl): reject item validation errors and unexpected documents
- `64304687d39012a86baec44ee44b5dcb57ef891b` — test(integrations): verify discovery workflows and retained-data boundaries
