import { expect, test, type Page, type APIRequestContext } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import en from '../../src/i18n/locales/en.json' with { type: 'json' }
import de from '../../src/i18n/locales/de.json' with { type: 'json' }
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }
const copies = { en, de, fr }
type Locale = keyof typeof copies
const shipment = {
  originCountry: 'DE',
  originPostalCode: '10115',
  destinationCountry: 'FR',
  destinationPostalCode: '75001',
  weightGrams: 1001,
  lengthMm: 300,
  widthMm: 200,
  heightMm: 100,
  quantity: 1,
  handover: 'any',
  speed: 'any',
}
async function write(
  request: APIRequestContext,
  path: string,
  data: unknown,
  method = 'POST',
  key = crypto.randomUUID(),
) {
  const token = (await (await request.get('/api/v1/auth/csrf')).json()).token
  return request.fetch('/api/v1' + path, {
    method,
    data,
    headers: { 'X-CSRF-Token': token, 'Idempotency-Key': key },
  })
}
async function login(page: Page, role: string) {
  await page.goto('/login')
  await page.getByTestId('language-switcher').getByRole('combobox').selectOption('en')
  await page.getByLabel(en.common.email).fill(role.toLowerCase() + '@e2e.shipwise.test')
  await page.getByLabel(en.auth.password).fill(process.env.E2E_PASSWORD!)
  await page.getByRole('button', { name: en.auth.login, exact: true }).click()
  await expect(page).not.toHaveURL(/\/login/)
}
async function fill(page: Page, locale: Locale) {
  const copy = copies[locale].quote
  for (const [label, value] of [
    [copy.originPostal, '10115'],
    [copy.destinationPostal, '75001'],
    [copy.weight, '1.001'],
    [copy.length, '30'],
    [copy.width, '20'],
    [copy.height, '10'],
  ])
    await page.getByRole('textbox', { name: label, exact: true }).fill(value)
  await page.getByLabel(copy.destinationCountry, { exact: true }).selectOption('FR')
}
for (const locale of ['en', 'de', 'fr'] as const) {
  test('guest comparison and validated selection in ' + locale, async ({ page }) => {
    const copy = copies[locale].quote
    await page.goto('/compare')
    await page.getByTestId('language-switcher').getByRole('combobox').selectOption(locale)
    await fill(page, locale)
    const result = page.waitForResponse(
      (r) => r.url().endsWith('/api/v1/quotes') && r.request().method() === 'POST',
    )
    await page.getByRole('button', { name: copy.search, exact: true }).click()
    const quote = await (await result).json()
    expect(quote.offers).toHaveLength(2)
    const first = [...quote.offers].sort((a, b) => a.price.totalMinor - b.price.totalMinor)[0]
    await expect(page.getByTestId('quote-price').first()).toHaveText(
      new Intl.NumberFormat(locale, { style: 'currency', currency: first.price.currency }).format(
        first.price.totalMinor / 100,
      ),
    )
    await page.getByRole('button', { name: copy.details, exact: true }).first().click()
    const drawer = page.getByRole('dialog')
    await expect(drawer).toBeVisible()
    await expect(drawer).toContainText(copy.noBooking)
    const accessibility = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
      .analyze()
    expect(accessibility.violations).toEqual([])
    await page.keyboard.press('Escape')
    await expect(drawer).not.toBeVisible()
    await page.getByLabel(copy.originCountry, { exact: true }).selectOption('FR')
    await page.getByRole('button', { name: copy.search, exact: true }).click()
    await expect(page.getByRole('heading', { name: copy.empty, exact: true })).toBeVisible()
    await page.getByRole('textbox', { name: copy.weight, exact: true }).fill('0')
    await page.getByRole('button', { name: copy.search, exact: true }).click()
    await expect(page.getByRole('alert')).toHaveText(copy.measureInvalid)
  })
}
test('customer quote ownership and guest session isolation', async ({ page, browser }) => {
  await login(page, 'CUSTOMER')
  await page.getByRole('link', { name: en.customer.compare, exact: true }).click()
  await expect(page).toHaveURL(/\/compare$/)
  const response = await write(page.request, '/quotes', shipment)
  expect(response.status()).toBe(201)
  const quote = await response.json()
  expect(quote.accessToken).toBeUndefined()
  expect((await page.request.get('/api/v1/quotes/' + quote.id)).ok()).toBe(true)
  const other = await browser.newContext()
  const foreign = await other.newPage()
  await login(foreign, 'FINANCE_MANAGER')
  expect((await foreign.request.get('/api/v1/quotes/' + quote.id)).status()).toBe(404)
  await other.close()
})
test('carrier administration, versioned tariffs and partial provider failure', async ({ page }) => {
  await login(page, 'CARRIER_MANAGER')
  await page.goto('/admin/carriers')
  await expect(page.getByRole('heading', { name: en.quote.adminTitle })).toBeVisible()
  expect((await write(page.request, '/admin/pricing-rules', {})).status()).toBe(403)
  await page
    .getByRole('button', { name: en.quote.toggle + ' ' + en.quote.DEV_STANDARD, exact: true })
    .click()
  await expect(page.getByRole('dialog')).toContainText(en.quote.DEV_STANDARD)
  await page.getByRole('dialog').getByRole('button', { name: en.common.save, exact: true }).click()
  await expect(page.getByRole('dialog')).not.toBeVisible()
  let quote = await (await write(page.request, '/quotes', shipment)).json()
  expect(quote.offers).toHaveLength(1)
  await page
    .getByRole('button', { name: en.quote.toggle + ' ' + en.quote.DEV_STANDARD, exact: true })
    .click()
  await expect(page.getByRole('dialog')).toContainText(en.quote.DEV_STANDARD)
  await page.getByRole('dialog').getByRole('button', { name: en.common.save, exact: true }).click()
  await expect(page.getByRole('dialog')).not.toBeVisible()
  const tariff = {
    serviceCode: 'DEV_STANDARD',
    version: 'e2e-invalid-response',
    baseMinor: 1000000,
    perKgMinor: 1000000,
    surchargeMinor: 0,
    currency: 'EUR',
    effectiveFrom: '2026-01-01T00:00:00Z',
  }
  expect((await write(page.request, '/admin/tariffs', tariff)).status()).toBe(201)
  quote = await (await write(page.request, '/quotes', { ...shipment, weightGrams: 15000 })).json()
  expect(quote.offers).toHaveLength(1)
  expect(
    quote.outcomes.some((item: { status: string }) => item.status === 'invalid_response'),
  ).toBe(true)
  expect(
    (
      await write(page.request, '/admin/tariffs', {
        ...tariff,
        version: 'e2e-restored',
        baseMinor: 400,
        perKgMinor: 50,
      })
    ).status(),
  ).toBe(201)
})
test('finance cannot manage carrier data', async ({ page }) => {
  await login(page, 'FINANCE_MANAGER')
  expect((await page.request.get('/api/v1/admin/carriers')).status()).toBe(403)
  expect(
    (await write(page.request, '/admin/carriers/DEV_POST', { enabled: false }, 'PATCH')).status(),
  ).toBe(403)
})
for (const width of [390, 768, 1440]) {
  test('comparison at ' + width + ' pixels and expired offer rejection', async ({ page }) => {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/compare')
    await page.getByTestId('language-switcher').getByRole('combobox').selectOption('en')
    await fill(page, 'en')
    await page.getByRole('button', { name: en.quote.search, exact: true }).click()
    await expect(page.getByTestId('quote-price').first()).toBeVisible()
    expect(
      await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBe(true)
    const accessibility = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
      .analyze()
    expect(accessibility.violations).toEqual([])
    await page.getByRole('heading', { level: 1 }).click()
    await page.screenshot({ path: 'test-results/phase2-compare-' + width + '.png', fullPage: true })
    await page.clock.install()
    await page.clock.fastForward('16:00')
    await expect(page.getByRole('alert')).toHaveText(en.quote.expired)
    await expect(
      page.getByRole('button', { name: en.quote.details, exact: true }).first(),
    ).toBeDisabled()
  })
}
