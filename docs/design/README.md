# Interface system and translation guide

The supplied `shipwise-ai-reference.png` guides the light canvas, compact sidebar, mint highlights, emerald primary actions and audit drawer. Displayed account counts, status distribution and events come from the protected API. Unsupported shipment, revenue, location, map and IP data are not invented. Audit actors remain account identifiers because the current API does not return actor names.

## Tokens and components

`apps/frontend/src/style.css` defines the Tailwind theme and CSS variables: white surfaces, blue-gray canvas/ink, emerald brand, semantic success/warning/error/info, a 4px spacing unit, control/card radii, subtle shadows and visible focus rings. System fonts are used; Inter is only a locally available preference. Responsive rules adapt navigation, forms, tables and drawers; long localized strings wrap. Reduced-motion preferences disable ornamental motion.

Shared Vue components include AppButton, AppInput, AppSelect, AppCard, AppTable, AppBadge, AppEmptyState, AppSkeleton, PageControls, PageHeader, FeedbackBox, LanguageSwitcher, UserMenu, SidebarNavigation and PublicHeader. AppModal uses a native dialog for drawers and confirmations, traps Tab, supports Escape, and restores trigger focus. Busy mutations prevent premature dismissal. Forms use explicit labels, translated validation and associated error descriptions.

Lucide icons are decorative beside visible labels or belong to explicitly named buttons. Its ISC and Feather MIT notices are preserved in `licenses/LUCIDE_LICENSE.txt`. BrandMark and ShipIllustration are original repository SVG code. The user supplied the visual reference; no premium templates or external font/image downloads are included.

## Add or change translations

1. Edit matching keys in `apps/frontend/src/i18n/locales/{en,de,fr}.json`. Keep placeholders consistent; use Vue I18n plural forms for counts. Put all UI copy in resources, including errors, validation, empty states and accessible names. Keep source identifiers and API codes English.
2. Run `npm run i18n:check`, unit tests and quality checks in the frontend container. The checker compares nonempty keys/placeholders and rejects untranslated static Vue template copy. Review dynamic text and translation quality manually as well.
3. For a new supported language, extend the locale registry/imports, native language options, browser detection and tests. English remains the fallback. Update backend locale validation and add a new additive database migration to extend the CHECK constraint; changing frontend resources alone does not enable a new account locale.
4. Add the corresponding backend `translations/account.{locale}.json` email copy and secure-mail tests. Preserve token links, expiry and single-use behavior. Extend OpenAPI locale enums, regenerate the client and update tests/documentation.
5. Exercise runtime switching, anonymous preference reload, authenticated preference persistence, translated validation, emails and role-restricted workflows in the browser. Check long strings at mobile, tablet and desktop widths.

The authenticated profile is authoritative after login. Anonymous localStorage holds only `shipwise.locale`, never credentials or tokens. Stable server problem codes map to translated messages; raw internal server details are not presented as translated product copy.
