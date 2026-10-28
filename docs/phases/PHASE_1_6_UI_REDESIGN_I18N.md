# Phase 1.6 — UI redesign and internationalization

Status: Implementation in progress. This phase does not complete Phase 1.5 or start Phase 2.

## Design direction

The user-supplied reference is preserved at `docs/design/shipwise-ai-reference.png`. It guides the light sidebar, blue-gray canvas, emerald actions, mint accents, spacious cards and audit detail drawer. It is a visual reference, not a source of business data or permission to fabricate metadata. All displayed counts and events come from the protected Phase 1 API. No IP, location, map, revenue or shipment data is invented.

Central Tailwind theme tokens and CSS variables define color, type, spacing, borders, radii, shadows, focus and responsive breakpoints. Vue components provide buttons, inputs/selects, cards, tables, badges, skeletons, empty states, pagination, language selection, page headers, navigation and account menus. A native-dialog component handles confirmations and side drawers with keyboard focus containment, Escape and focus restoration. Original SVG ship illustrations are code assets, not premium templates.

## Localization architecture

Vue I18n Composition API loads complete EN, DE and FR JSON resources. English is the fallback. Source identifiers, comments, tests, documentation, API paths/fields, permission identifiers and event codes remain English and language-neutral. Only translation resources and localized email templates contain German/French user-facing copy; this supersedes the original Phase 1 English-only UI restriction.

Anonymous locale selection uses the non-sensitive `shipwise.locale` preference, then browser language on first visit, then English. Account locale is authoritative after login/refresh. Runtime language switching persists authenticated preferences through the existing CSRF-protected `PATCH /api/v1/me`; it neither logs users out nor changes their roles or session version. Locale updates are allowlisted to `en`, `de`, `fr`. Registration and staff invitation can select the recipient locale. Password reset uses the recipient's stored preference.

The backend stores `users.preferred_locale` through an additive migration and ORM XML mapping. Account email copy is loaded from `apps/backend/translations/account.{locale}.json`. Tokens remain hashed, single-use and expiring; links retain fragment delivery. Email remains synchronous; no outbox is introduced. Stable Problem Details codes let the frontend translate failures without parsing or displaying English server details. Dates use the selected locale with UTC audit timestamps; numbers use locale-aware Intl formatting.

## Delivery and verification criteria

- Redesign every existing public, customer and administrative route with the shared visual system.
- Preserve all cookie, CSRF, RBAC, session revocation, invitation, audit and last-administrator protections.
- Verify translation key/placeholder completeness and prevent hardcoded Vue user-facing copy.
- Test anonymous detection/persistence, account locale precedence, runtime switching and fallback.
- Test EN/DE/FR verification/reset/invitation email copy and account locale persistence.
- Exercise translated account/admin workflows in Playwright at mobile, tablet and desktop sizes; verify keyboard drawer/confirmation behavior and empty-result consistency.
- Run backend/frontend quality gates, OpenAPI synchronization, dependency audits and actual GitHub Actions before claiming completion.

## Release boundaries

Production staff MFA restrictions remain in place. Phase 1.5 deployment, durable email, backup/restore, monitoring and compliance work remain planned. Carrier integrations, quotations, pricing, orders, payments, labels and AI are outside this task. A separate request is required for Phase 2.

Final execution evidence and remaining limitations will be recorded here and in `docs/verification.md` after checks actually run.
