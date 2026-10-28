import { expect, test, type Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import en from '../../src/i18n/locales/en.json' with { type: 'json' }
import de from '../../src/i18n/locales/de.json' with { type: 'json' }
import fr from '../../src/i18n/locales/fr.json' with { type: 'json' }

const copies = { en, de, fr }
type Locale = keyof typeof copies
const password = process.env.E2E_PASSWORD
if (!password) throw new Error('E2E_PASSWORD must be supplied for isolated test fixtures.')
const staffEmail = (role: string) => role.toLowerCase() + '@e2e.shipwise.test'
const languageControl = (page: Page) => page.getByTestId('language-switcher').getByRole('combobox')
async function currentLocale(page: Page): Promise<Locale> {
  return ((await page.locator('html').getAttribute('lang')) || 'en') as Locale
}
async function language(page: Page, locale: Locale, authenticated = false) {
  const previous = await currentLocale(page)
  const persisted =
    authenticated && previous !== locale
      ? page.waitForResponse(
          (response) =>
            response.url().endsWith('/api/v1/me') && response.request().method() === 'PATCH',
        )
      : undefined
  await languageControl(page).selectOption(locale)
  await expect(page.locator('html')).toHaveAttribute('lang', locale)
  if (persisted) expect((await persisted).ok()).toBe(true)
  await expect(languageControl(page)).toBeEnabled()
}
async function login(
  page: Page,
  email: string,
  locale: Locale = 'en',
  accountPassword = password!,
) {
  await page.goto('/login')
  await language(page, locale)
  await page.getByLabel(copies[locale].common.email).fill(email)
  await page.getByLabel(copies[locale].auth.password).fill(accountPassword)
  await page.getByRole('button', { name: copies[locale].auth.login, exact: true }).click()
  await expect(page).not.toHaveURL(/\/login(?:\?|$)/)
  await language(page, locale, true)
}
async function logout(page: Page, locale: Locale) {
  await page.getByLabel(copies[locale].common.accountMenu, { exact: true }).click()
  await page.getByRole('button', { name: copies[locale].auth.logout, exact: true }).click()
  await expect(page).toHaveURL(/\/login$/)
}
async function mailLink(
  page: Page,
  email: string,
  path: string,
  expectedSubject?: string,
): Promise<string> {
  const mailpit = process.env.MAILPIT_URL || 'http://mailpit:8025'
  let text = ''
  await expect
    .poll(async () => {
      const list = await (await page.request.get(mailpit + '/api/v1/messages')).json()
      for (const message of list.messages.filter((item: { To: { Address: string }[] }) =>
        item.To.some((to) => to.Address === email),
      )) {
        const detail = await (
          await page.request.get(mailpit + '/api/v1/message/' + message.ID)
        ).json()
        if (detail.Text.includes('/' + path + '#token=')) {
          text = detail.Text
          return !expectedSubject || detail.Subject === expectedSubject
        }
      }
      return false
    })
    .toBe(true)
  const match = text.match(new RegExp('/' + path + '#token=[a-f0-9]{64}'))
  if (!match) throw new Error('Expected secure account email link')
  return match[0]
}
async function accessible(page: Page) {
  const result = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
    .analyze()
  expect(
    result.violations.map((item) => ({
      id: item.id,
      description: item.description,
      nodes: item.nodes.map((node) => node.target),
    })),
  ).toEqual([])
}
async function noOverflow(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  )
}

const subjects = {
  en: {
    verify: 'Verify your ShipWise email',
    reset: 'Reset your ShipWise password',
    invite: 'You are invited to ShipWise',
  },
  de: {
    verify: 'Bestätige deine E-Mail-Adresse bei ShipWise',
    reset: 'Setze dein ShipWise-Passwort zurück',
    invite: 'Du bist zu ShipWise eingeladen',
  },
  fr: {
    verify: 'Confirmez votre adresse e-mail ShipWise',
    reset: 'Réinitialisez votre mot de passe ShipWise',
    invite: 'Vous êtes invité à rejoindre ShipWise',
  },
}
for (const locale of ['en', 'de', 'fr'] as const) {
  test(`customer onboarding, recovery and account pages in ${locale}`, async ({ page }) => {
    const copy = copies[locale],
      email = `buyer-${locale}-${Date.now()}@e2e.shipwise.test`
    await page.goto('/register')
    await language(page, locale)
    await page.getByLabel(copy.common.name).fill('Journey Customer')
    await page.getByLabel(copy.common.email).fill(email)
    await page.getByLabel(copy.auth.newPassword).fill(password!)
    await accessible(page)
    await page.getByRole('button', { name: copy.common.continue, exact: true }).click()
    await expect(page.getByRole('status')).toContainText(copy.auth.checkEmail)
    await page.goto(await mailLink(page, email, 'verify-email', subjects[locale].verify))
    await expect(page).toHaveURL(/\/verify-email$/)
    await page.getByRole('button', { name: copy.common.continue, exact: true }).click()
    await expect(page.getByRole('status')).toContainText(copy.auth.verified)
    await login(page, email, locale)
    await expect(page).toHaveURL(/\/app$/)
    await expect(page.getByText(copy.customer.verified)).toBeVisible()
    await page.getByRole('link', { name: copy.navigation.profile, exact: true }).first().click()
    await page.getByLabel(copy.common.name).fill('Updated Customer')
    await page.getByRole('button', { name: copy.common.save, exact: true }).click()
    await expect(page.getByRole('status')).toContainText(copy.common.profileSaved)
    await page.reload()
    await expect(page.locator('html')).toHaveAttribute('lang', locale)
    expect((await (await page.request.get('/api/v1/me')).json()).preferredLocale).toBe(locale)
    await page.goto('/app/settings')
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(copy.navigation.settings)
    await accessible(page)
    await logout(page, locale)
    await page.goto('/app/profile')
    await expect(page).toHaveURL(/\/login/)
    await page.getByRole('link', { name: copy.auth.forgot, exact: true }).click()
    await page.getByLabel(copy.common.email).fill(email)
    await page.getByRole('button', { name: copy.common.continue, exact: true }).click()
    await expect(page.getByRole('status')).toContainText(copy.auth.checkEmail)
    await page.goto(await mailLink(page, email, 'reset-password', subjects[locale].reset))
    await page.getByLabel(copy.auth.newPassword).fill(password! + 'A')
    await page.getByRole('button', { name: copy.common.continue, exact: true }).click()
    await expect(page.getByRole('status')).toContainText(copy.auth.resetDone)
    await login(page, email, locale, password! + 'A')
    await expect(page).toHaveURL(/\/app$/)
  })
  test(`localized invitation, safe role changes and audit details in ${locale}`, async ({
    page,
  }) => {
    const copy = copies[locale],
      email = `invited-${locale}-${Date.now()}@e2e.shipwise.test`
    await login(page, staffEmail('SUPER_ADMIN'), locale)
    await page.getByRole('link', { name: copy.navigation.staff, exact: true }).click()
    await page.getByRole('button', { name: copy.staff.invite, exact: true }).click()
    const invite = page.getByRole('dialog', { name: copy.staff.inviteTitle, exact: true })
    await invite.getByLabel(copy.common.name).fill('Invited Colleague')
    await invite.getByLabel(copy.staff.workEmail).fill(email)
    await invite.getByLabel(copy.staff.inviteLanguage).selectOption(locale)
    await invite.getByLabel(copy.roles.FINANCE_MANAGER, { exact: true }).check()
    await accessible(page)
    await invite.getByRole('button', { name: copy.staff.send, exact: true }).click()
    await expect(invite).not.toBeVisible()
    await expect(page.getByRole('status').filter({ hasText: copy.staff.invited })).toBeVisible()
    await page.getByLabel(copy.staff.search).fill(email)
    await page.getByRole('button', { name: copy.common.search, exact: true }).click()
    await expect(page.getByRole('row')).toHaveCount(2)
    await page.getByRole('row').filter({ hasText: email }).getByRole('button').click()
    const detail = page.getByRole('dialog', { name: 'Invited Colleague', exact: true })
    await detail.getByLabel(copy.roles.QA_ENGINEER, { exact: true }).check()
    await detail.getByRole('button', { name: copy.staff.saveRoles, exact: true }).click()
    const confirmation = page.getByRole('dialog', { name: copy.staff.confirmRoles, exact: true })
    await expect(confirmation).toBeVisible()
    await confirmation.getByRole('button', { name: copy.common.continue, exact: true }).click()
    await expect(confirmation).not.toBeVisible()
    await expect(detail.getByRole('status')).toContainText(copy.staff.updated)
    await detail.getByRole('button', { name: copy.common.close, exact: true }).click()
    await page.getByRole('link', { name: copy.navigation.audit, exact: true }).click()
    await expect(
      page
        .getByRole('table', { name: copy.navigation.audit })
        .getByText(copy.events.staff_invited, { exact: true })
        .first(),
    ).toBeVisible()
    const openEvent = page
      .getByRole('button', {
        name: copy.audit.open.replace('{event}', copy.events.staff_invited),
        exact: true,
      })
      .first()
    await openEvent.click()
    const audit = page.getByRole('dialog', { name: copy.events.staff_invited, exact: true })
    await expect(audit.getByRole('button', { name: copy.common.close, exact: true })).toBeFocused()
    await accessible(page)
    await page.keyboard.press('Escape')
    await expect(audit).not.toBeVisible()
    await expect(openEvent).toBeFocused()
    await page
      .getByLabel(copy.audit.actor, { exact: true })
      .fill('00000000-0000-0000-0000-000000000000')
    await page.getByRole('button', { name: copy.audit.search, exact: true }).click()
    await expect(page.getByText(copy.audit.empty, { exact: true })).toBeVisible()
    await expect(page.getByRole('dialog')).not.toBeVisible()
    await logout(page, locale)
    await page.goto(await mailLink(page, email, 'accept-invitation', subjects[locale].invite))
    await page.getByLabel(copy.auth.newPassword).fill(password!)
    await page.getByRole('button', { name: copy.common.continue, exact: true }).click()
    await expect(page.getByRole('status')).toContainText(copy.auth.accepted)
    await login(page, email, locale)
    await expect(page).toHaveURL(/\/admin\/finance$/)
  })
  test(`all administration routes and customer/public page copy in ${locale}`, async ({ page }) => {
    const copy = copies[locale]
    await login(page, staffEmail('SUPER_ADMIN'), locale)
    const routes = {
      '/admin': copy.navigation.overview,
      '/admin/staff': copy.navigation.staff,
      '/admin/access-control': copy.navigation.access,
      '/admin/audit': copy.navigation.audit,
      '/admin/qa': copy.navigation.qa,
      '/admin/finance': copy.navigation.finance,
      '/admin/marketing': copy.navigation.marketing,
      '/admin/operations': copy.navigation.operations,
      '/admin/support': copy.navigation.support,
      '/admin/carriers': copy.navigation.carriers,
    }
    for (const [path, title] of Object.entries(routes)) {
      await page.goto(path)
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(title)
      await expect(page.locator('html')).toHaveAttribute('lang', locale)
      await noOverflow(page)
    }
    await page.goto('/admin')
    const counts = await (await page.request.get('/api/v1/admin/dashboard')).json()
    await expect(
      page.locator('.metric-card').filter({ hasText: copy.admin.customers }).locator('strong'),
    ).toHaveText(new Intl.NumberFormat(locale).format(counts.customerCount))
    await page.goto('/admin/audit')
    await accessible(page)
    await logout(page, locale)
    for (const [path, title] of [
      ['/', copy.home.title],
      ['/login', copy.auth.welcome],
      ['/register', copy.auth.registerTitle],
      ['/verify-email', copy.auth.verifyTitle],
      ['/forgot-password', copy.auth.forgotTitle],
      ['/reset-password', copy.auth.resetTitle],
      ['/accept-invitation', copy.auth.inviteTitle],
    ]) {
      await page.goto(path)
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(title)
      await noOverflow(page)
    }
  })
}
for (const [role, path] of [
  ['FINANCE_MANAGER', 'finance'],
  ['QA_ENGINEER', 'qa'],
]) {
  test(role + ' keeps backend permission boundaries after a language switch', async ({ page }) => {
    await login(page, staffEmail(role), 'de')
    await expect(page).toHaveURL(new RegExp('/admin/' + path + '$'))
    expect((await page.request.get('/api/v1/admin/departments/marketing')).status()).toBe(403)
    await language(page, 'fr', true)
    expect((await page.request.get('/api/v1/admin/staff')).status()).toBe(403)
    await page.goto('/admin/marketing')
    await expect(page).toHaveURL(/\/forbidden$/)
    await expect(page.getByText(fr.error.title, { exact: true })).toBeVisible()
    await page.goto('/admin/' + path)
    await logout(page, 'fr')
    await page.goto('/admin/' + path)
    await expect(page).toHaveURL(/\/login/)
  })
}
for (const [width, height] of [
  [390, 844],
  [768, 1024],
  [1440, 1000],
]) {
  test(`responsive audit navigation and keyboard drawer at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height })
    await login(page, staffEmail('SUPER_ADMIN'), 'fr')
    if (width <= 1080) {
      await page.getByRole('button', { name: fr.common.openNavigation, exact: true }).click()
      const drawer = page.getByRole('dialog', { name: fr.common.workspace, exact: true })
      await expect(drawer.getByRole('button', { name: fr.common.close, exact: true })).toBeFocused()
      await drawer.getByRole('link', { name: fr.navigation.audit, exact: true }).click()
      await expect(drawer).not.toBeVisible()
    } else await page.getByRole('link', { name: fr.navigation.audit, exact: true }).click()
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(fr.navigation.audit)
    await noOverflow(page)
    await accessible(page)
    await page
      .getByRole('button', {
        name: fr.audit.open.replace('{event}', fr.events.auth_login),
        exact: true,
      })
      .first()
      .click()
    const drawer = page.getByRole('dialog', { name: fr.events.auth_login, exact: true })
    await expect(drawer).toBeVisible()
    for (let index = 0; index < 6; index++) {
      await page.keyboard.press('Tab')
      expect(await drawer.evaluate((element) => element.contains(document.activeElement))).toBe(
        true,
      )
    }
    await noOverflow(page)
    await accessible(page)
    await drawer.getByRole('button', { name: fr.audit.raw, exact: true }).click()
    await expect(drawer.locator('pre')).not.toContainText('ip_address')
    await drawer.getByRole('button', { name: fr.common.details, exact: true }).click()
    await page.screenshot({ path: `test-results/redesign-audit-${width}.png`, fullPage: true })
    await page.keyboard.press('Escape')
    await expect(drawer).not.toBeVisible()
  })
}
test('translated validation and stored anonymous locale survive reload', async ({ page }) => {
  await page.goto('/login')
  await language(page, 'de')
  await page.getByRole('button', { name: de.auth.login, exact: true }).click()
  await expect(page.getByRole('alert')).toHaveText(de.errors.request_invalid)
  await expect(page.getByText(de.errors.email_invalid, { exact: true })).toBeVisible()
  await page.reload()
  await expect(page.locator('html')).toHaveAttribute('lang', 'de')
  await language(page, 'fr')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Heureux de vous revoir.')
  await accessible(page)
})

test('translated staff suspension and last-administrator safeguards', async ({ page }) => {
  const copy = de
  await login(page, staffEmail('SUPER_ADMIN'), 'de')
  const owner = await (await page.request.get('/api/v1/auth/me')).json()
  await page.goto('/admin/staff')
  async function account(email: string, name: string) {
    await page.getByLabel(copy.staff.search).fill(email)
    await page.getByRole('button', { name: copy.common.search, exact: true }).click()
    await expect(page.getByRole('row')).toHaveCount(2)
    await page.getByRole('row').filter({ hasText: email }).getByRole('button').click()
    return page.getByRole('dialog', { name, exact: true })
  }
  const self = await account(owner.email, owner.name)
  await self.getByRole('button', { name: copy.staff.suspend, exact: true }).click()
  let confirm = page.getByRole('dialog', { name: copy.staff.confirmStatus, exact: true })
  await confirm.getByRole('button', { name: copy.common.continue, exact: true }).click()
  await expect(confirm).not.toBeVisible()
  await expect(self.getByRole('alert')).toHaveText(copy.errors.last_admin)
  expect((await (await page.request.get('/api/v1/auth/me')).json()).status).toBe('active')
  await self.getByRole('button', { name: copy.common.close, exact: true }).click()
  const financeData = await (
    await page.request.get(
      '/api/v1/admin/staff?search=' + encodeURIComponent(staffEmail('FINANCE_MANAGER')),
    )
  ).json()
  const finance = await account(staffEmail('FINANCE_MANAGER'), financeData.items[0].name)
  for (const target of ['suspended', 'active']) {
    await finance
      .getByRole('button', {
        name: target === 'suspended' ? copy.staff.suspend : copy.staff.reactivate,
        exact: true,
      })
      .click()
    confirm = page.getByRole('dialog', { name: copy.staff.confirmStatus, exact: true })
    await confirm.getByRole('button', { name: copy.common.continue, exact: true }).click()
    await expect(confirm).not.toBeVisible()
    await expect(finance.locator('.app-badge').first()).toHaveText(
      copy.statuses[target as 'suspended' | 'active'],
    )
    expect(
      (await (await page.request.get('/api/v1/admin/staff/' + financeData.items[0].id)).json())
        .status,
    ).toBe(target)
  }
})
