import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSession } from './session'
import { api, ApiError, type Profile } from '../api/client'
vi.mock('../api/client', async original => ({ ...await original<typeof import('../api/client')>(), api: vi.fn() }))
const profile: Profile = { id: 'account-id', email: 'test@example.test', name: 'Test Person', kind: 'staff', status: 'active', createdAt: '2026-01-01T00:00:00Z', roles: ['FINANCE_MANAGER'], permissions: ['finance.dashboard.view'] }
describe('session state', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.mocked(api).mockReset() })
  it('defaults to denied permissions', () => { expect(useSession().can('finance.dashboard.view')).toBe(false) })
  it('uses backend permissions after login', async () => { vi.mocked(api).mockResolvedValue(profile); const session = useSession(); await session.login(profile.email, 'test-password'); expect(session.can('finance.dashboard.view')).toBe(true); expect(session.can('marketing.dashboard.view')).toBe(false) })
  it('clears identity on logout', async () => { vi.mocked(api).mockResolvedValue(profile); const session = useSession(); await session.login(profile.email, 'test-password'); await session.logout(); expect(session.user).toBeNull() })
  it('treats 401 as an anonymous session', async () => { vi.mocked(api).mockRejectedValue(new ApiError('Sign in', 401)); const session = useSession(); await session.refresh(); expect(session.initialized).toBe(true); expect(session.user).toBeNull() })
  it('propagates server errors', async () => { vi.mocked(api).mockRejectedValue(new ApiError('Unavailable', 503)); await expect(useSession().refresh()).rejects.toThrow('Unavailable') })
})
