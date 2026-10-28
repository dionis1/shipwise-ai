import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import LanguageSwitcher from './LanguageSwitcher.vue'
import { useSession } from '../stores/session'
import { setLocale } from '../i18n'
import { api, ApiError, type Profile } from '../api/client'
vi.mock('../api/client', async (original) => ({
  ...(await original<typeof import('../api/client')>()),
  api: vi.fn(),
}))
const profile: Profile = {
  id: 'id',
  email: 'test@example.test',
  name: 'Test Person',
  kind: 'staff',
  status: 'active',
  roles: ['FINANCE_MANAGER'],
  permissions: ['finance.dashboard.view'],
  preferredLocale: 'en',
  createdAt: '',
}
beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
  setLocale('en')
  vi.mocked(api).mockReset()
})
describe('language selection', () => {
  it('switches anonymous copy and remembers the locale without an API request', async () => {
    const wrapper = mount(LanguageSwitcher)
    await wrapper.get('select').setValue('de')
    await flushPromises()
    expect(wrapper.get('label').text()).toBe('Sprache')
    expect(localStorage.getItem('shipwise.locale')).toBe('de')
    expect(api).not.toHaveBeenCalled()
  })
  it('persists authenticated preferences without losing identity or permissions', async () => {
    const session = useSession()
    session.user = profile
    vi.mocked(api).mockResolvedValue({ ...profile, preferredLocale: 'fr' })
    const wrapper = mount(LanguageSwitcher)
    await wrapper.get('select').setValue('fr')
    await flushPromises()
    expect(api).toHaveBeenCalledWith('/me', 'PATCH', { preferredLocale: 'fr' })
    expect(session.user?.permissions).toEqual(profile.permissions)
    expect(wrapper.get('label').text()).toBe('Langue')
  })
  it('restores the previous preference if the server rejects the update', async () => {
    useSession().user = profile
    vi.mocked(api).mockRejectedValue(new ApiError('internal detail', 403, 'forbidden'))
    const wrapper = mount(LanguageSwitcher)
    await wrapper.get('select').setValue('de')
    await flushPromises()
    expect(wrapper.get('select').element.value).toBe('en')
    expect(wrapper.get('[role="alert"]').text()).toBe('You do not have permission for this action.')
    expect(wrapper.text()).not.toContain('internal detail')
  })
  it('uses the saved account preference after authentication', async () => {
    setLocale('de')
    vi.mocked(api).mockResolvedValue({ ...profile, preferredLocale: 'fr' })
    await useSession().login(profile.email, 'test-password')
    expect(document.documentElement.lang).toBe('fr')
  })
})
