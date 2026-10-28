import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { router, landingFor, adminLinks } from './index'
import { useSession } from '../stores/session'
beforeEach(() => {
  setActivePinia(createPinia())
  useSession().initialized = true
})
describe('permission-aware routes', () => {
  it('redirects anonymous staff access to login', async () => {
    await router.push('/admin/finance')
    expect(router.currentRoute.value.path).toBe('/login')
  })
  it('selects an authorized department after login', () => {
    expect(landingFor(['finance.dashboard.view'], 'staff')).toBe('/admin/finance')
    expect(landingFor([], 'customer')).toBe('/app')
  })
  it('rejects an unauthorized department', async () => {
    const session = useSession()
    session.user = {
      id: 'id',
      email: 'f@example.test',
      name: 'Finance',
      status: 'active',
      kind: 'staff',
      createdAt: '',
      roles: ['FINANCE_MANAGER'],
      permissions: ['finance.dashboard.view'],
    }
    await router.push('/admin/marketing')
    expect(router.currentRoute.value.path).toBe('/forbidden')
  })
  it('defines a backend permission for every admin destination', () => {
    expect(adminLinks.every((link) => link.permission.includes('.'))).toBe(true)
  })
  it('does not swallow a failed permission refresh as an authenticated user', async () => {
    const session = useSession()
    session.initialized = false
    vi.spyOn(session, 'refresh').mockRejectedValue(new Error('offline'))
    await router.push('/admin')
    expect(router.currentRoute.value.path).toBe('/forbidden')
  })
})
