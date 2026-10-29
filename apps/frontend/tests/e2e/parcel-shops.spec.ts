import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import en from '../../src/i18n/locales/en.json' with { type: 'json' }
import de from '../../src/i18n/locales/de.json' with { type: 'json' }
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }
const copies = { en, de, fr }
for (const [locale, width] of [
  ['en', 1440],
  ['de', 768],
  ['fr', 390],
] as const) {
  test('parcel-shop unavailable and mocked results in ' + locale, async ({ page }) => {
    const copy = copies[locale]
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/parcel-shops')
    await page.getByTestId('language-switcher').getByRole('combobox').selectOption(locale)
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(copy.shops.title)
    await page.getByRole('button', { name: copy.shops.search, exact: true }).click()
    await expect(page.getByRole('alert')).toHaveText(copy.shops.searchInvalid)
    await page.getByRole('textbox', { name: copy.shops.postalCode, exact: true }).fill('10115')
    await page.getByRole('button', { name: copy.shops.search, exact: true }).click()
    await expect(page.getByTestId('shop-outcome').first()).toContainText(
      copy.sandbox.status.not_configured,
    )
    await expect(page.getByTestId('shop-outcome').last()).toContainText(
      copy.sandbox.status.specification_unavailable,
    )
    await expect(
      page.getByRole('heading', { name: copy.shops.unavailable, exact: true }),
    ).toBeVisible()
    await page.screenshot({
      path: 'test-results/sandbox-parcel-shops-' + locale + '.png',
      fullPage: true,
    })
    // Synthetic fixtures exist only in Playwright interception, never in the runtime adapter.
    await page.route('**/api/v1/parcel-shops?**', (route) =>
      route.fulfill({
        json: {
          items: [
            {
              carrier: 'DPD',
              id: 'synthetic-e2e-1',
              name: 'Synthetic Test Shop',
              address: 'Synthetic Street 1',
              postalCode: '10115',
              city: 'Berlin',
              country: 'DE',
              latitude: null,
              longitude: null,
              services: ['100'],
              retrievedAt: '2026-10-29T12:00:00Z',
              source: 'dpd_sandbox',
            },
          ],
          outcomes: [{ carrier: 'DPD', status: 'available' }],
        },
      }),
    )
    await page.getByLabel(copy.shops.carrier, { exact: true }).selectOption('DPD')
    await page.getByRole('button', { name: copy.shops.search, exact: true }).click()
    await expect(
      page.getByRole('heading', { name: 'Synthetic Test Shop', exact: true }),
    ).toBeVisible()
    await expect(page.locator('.parcel-shop-list')).toContainText(copy.shops.dpd_sandbox)
    await expect(page.locator('.parcel-shop-list')).not.toContainText('€')
    expect(
      await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBe(true)
    const accessibility = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
      .analyze()
    expect(accessibility.violations).toEqual([])
    await page.route('**/api/v1/parcel-shops?**', (route) =>
      route.fulfill({ json: { items: [], outcomes: [{ carrier: 'DPD', status: 'available' }] } }),
    )
    await page.getByRole('button', { name: copy.shops.search, exact: true }).click()
    await expect(page.getByRole('heading', { name: copy.shops.empty, exact: true })).toBeVisible()
    await page.route('**/api/v1/parcel-shops?**', (route) =>
      route.fulfill({ status: 429, json: { code: 'rate_limited' } }),
    )
    await page.getByRole('button', { name: copy.shops.search, exact: true }).click()
    await expect(page.getByRole('alert')).toHaveText(copy.errors.rate_limited)
  })
}
test('sandbox diagnostics remain disabled without configuration and preserve permission checks', async ({
  page,
}) => {
  await page.goto('/login')
  await page.getByTestId('language-switcher').getByRole('combobox').selectOption('en')
  await page.getByLabel(en.common.email).fill('carrier_manager@e2e.shipwise.test')
  await page.getByLabel(en.auth.password).fill(process.env.E2E_PASSWORD!)
  await page.getByRole('button', { name: en.auth.login, exact: true }).click()
  await expect(page).not.toHaveURL(/\/login/)
  await page.goto('/admin/carriers')
  await expect(page.getByRole('heading', { name: en.sandbox.title, exact: true })).toBeVisible()
  const dpd = page
    .getByTestId('sandbox-row')
    .filter({ has: page.getByRole('heading', { name: 'DPD', exact: true }) })
  await expect(dpd).toContainText(en.sandbox.status.not_configured)
  await expect(
    dpd.getByRole('button', { name: en.sandbox.operation.authentication + ' DPD', exact: true }),
  ).toBeDisabled()
  await expect(dpd).toContainText(en.sandbox.liveNotApproved)
  expect((await page.request.get('/api/v1/admin/carrier-sandboxes')).ok()).toBe(true)
})
