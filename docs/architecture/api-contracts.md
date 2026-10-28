# API contracts

`docs/api/openapi.json` is the versioned OpenAPI 3.0.3 contract. All implemented endpoints are under `/api/v1`. Generate models with `npm run api:generate` from apps/frontend, then commit generated.ts alongside the contract. CI compares regeneration and checks Symfony routes against contract methods/paths.

Request field allowlists reject unexpected keys. Symfony Validator validates field lengths/email; domain rules validate lifecycle constraints. Explicit profile DTOs and read models omit hashes and token secrets. Failures use RFC 9457-compatible Problem Details with correct HTTP statuses and X-Request-ID; internal errors have a generic detail. Timestamps are normalized to ISO 8601 UTC by response adapters. Pagination returns items/page/pageSize/total; tables have deterministic server ordering and bounded page size 20. Query filters are explicit allowlists.

For cookie-authenticated writes first GET /auth/csrf, then send X-CSRF-Token plus the same session cookie. Use a same-origin proxy; no permissive CORS is configured. Authentication endpoints return generic failure messages. Unknown routes and methods produce JSON problems under /api.
