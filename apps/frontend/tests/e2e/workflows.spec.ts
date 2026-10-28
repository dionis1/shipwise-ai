import { expect, test, type Page } from '@playwright/test'
const password = process.env.E2E_PASSWORD
if (!password) throw new Error('E2E_PASSWORD must be supplied for isolated test fixtures.')
const staffEmail = (role: string) => role.toLowerCase() + '@e2e.shipwise.test'
async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email address').fill(email)
  await page.getByLabel('Password', { exact: true }).fill(password!)
  await page.getByRole('button', { name: 'Sign in →' }).click()
}
async function mailLink(page: Page, email: string, path: string): Promise<string> {
  const mailpit = process.env.MAILPIT_URL || 'http://mailpit:8025'
  let text = ''
  await expect
    .poll(async () => {
      const response = await page.request.get(mailpit + '/api/v1/messages')
      const list = await response.json()
      const message = list.messages.find((m: { To: { Address: string }[] }) =>
        m.To.some((to) => to.Address === email),
      )
      if (!message) return false
      const detail = await (
        await page.request.get(mailpit + '/api/v1/message/' + message.ID)
      ).json()
      text = detail.Text
      return text.includes(path + '#token=')
    })
    .toBe(true)
  const match = text.match(new RegExp('/' + path + '#token=[a-f0-9]{64}'))
  if (!match) throw new Error('Expected account email link')
  return match[0]
}
test('customer registers, verifies email, signs in, edits profile and signs out', async ({
  page,
}) => {
  const email = 'buyer-' + Date.now() + '@e2e.shipwise.test'
  await page.goto('/register')
  await page.getByLabel('Full name').fill('Journey Customer')
  await page.getByLabel('Email address').fill(email)
  await page.getByLabel('New password').fill(password!)
  await page.getByRole('button', { name: 'Continue →' }).click()
  await expect(page.getByRole('status')).toContainText('check your email')
  await page.goto(await mailLink(page, email, 'verify-email'))
  await page.getByRole('button', { name: 'Continue →' }).click()
  await expect(page.getByRole('status')).toContainText('Email verified')
  await login(page, email)
  await expect(page).toHaveURL(/\/app$/)
  await page.getByRole('link', { name: 'Profile', exact: true }).click()
  await page.getByLabel('Full name').fill('Updated Customer')
  await page.getByRole('button', { name: 'Save changes' }).click()
  await expect(page.getByRole('status')).toContainText('Profile saved')
  await page.getByRole('button', { name: 'Sign out' }).click()
  await expect(page).toHaveURL(/\/login$/)
  await page.goto('/app/profile')
  await expect(page).toHaveURL(/\/login/)
})
test('Super Administrator invites staff, assigns roles and inspects the audit trail', async ({
  page,
}) => {
  await login(page, staffEmail('SUPER_ADMIN'))
  await expect(page).toHaveURL(/\/admin$/)
  await page.getByRole('link', { name: 'Team members', exact: true }).click()
  await page.getByRole('button', { name: '+ Invite team member' }).click()
  const email = 'invited-' + Date.now() + '@e2e.shipwise.test'
  await page.getByLabel('Full name').fill('Invited Colleague')
  await page.getByLabel('Work email').fill(email)
  await page.getByLabel('FINANCE MANAGER', { exact: true }).check()
  await page.getByRole('button', { name: 'Send invitation' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Invitation sent' })).toBeVisible()
  await page.getByLabel('Search team').fill(email)
  await page.getByRole('button', { name: 'Search', exact: true }).click()
  await expect(page.getByRole('row')).toHaveCount(2)
  await page
    .getByRole('row')
    .filter({ hasText: email })
    .getByRole('button', { name: 'View details →' })
    .click()
  await page.getByLabel('QA ENGINEER', { exact: true }).check()
  page.once('dialog', (dialog) => dialog.accept())
  await page.getByRole('button', { name: 'Save roles' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Account updated' })).toBeVisible()
  await page.getByRole('link', { name: 'Audit trail', exact: true }).click()
  await expect(page.getByText('staff.invited', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Sign out' }).click()
  await expect(page).toHaveURL(/\/login$/)
  await page.goto(await mailLink(page, email, 'accept-invitation'))
  await page.getByLabel('New password').fill(password!)
  await page.getByRole('button', { name: 'Continue →' }).click()
  await expect(page.getByRole('status')).toContainText('Invitation accepted')
  await login(page, email)
  await expect(page).toHaveURL(/\/admin\/finance$/)
})
for (const [role, path] of [
  ['FINANCE_MANAGER', 'finance'],
  ['QA_ENGINEER', 'qa'],
]) {
  test(
    role + ' can access its department and is denied an unauthorized dashboard',
    async ({ page }) => {
      await login(page, staffEmail(role))
      await expect(page).toHaveURL(new RegExp('/admin/' + path + '$'))
      await expect(page.getByRole('heading', { level: 1 })).toContainText(
        path === 'finance' ? 'Finance' : 'Quality assurance',
      )
      const response = await page.request.get('/api/v1/admin/departments/marketing')
      expect(response.status()).toBe(403)
      await page.goto('/admin/marketing')
      await expect(page).toHaveURL(/\/forbidden$/)
      await page.goto('/admin/' + path)
      await page.getByRole('button', { name: 'Sign out' }).click()
      await expect(page).toHaveURL(/\/login$/)
      await page.goto('/admin/' + path)
      await expect(page).toHaveURL(/\/login/)
    },
  )
}
test('landing and administration remain usable on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/')
  await expect(page.getByRole('heading', { name: /Great journeys/ })).toBeVisible()
  await login(page, staffEmail('FINANCE_MANAGER'))
  await page.getByRole('button', { name: 'Toggle navigation' }).click()
  await expect(page.getByRole('navigation', { name: 'Administration' })).toBeVisible()
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  )
})
