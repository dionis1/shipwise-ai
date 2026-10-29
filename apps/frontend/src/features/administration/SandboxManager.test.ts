import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, expect, it, vi } from 'vitest'
import SandboxManager from './SandboxManager.vue'
import { api } from '../../api/client'
import { setLocale } from '../../i18n'
vi.mock('../../api/client', async (original) => ({
  ...(await original<typeof import('../../api/client')>()),
  api: vi.fn(),
}))
vi.mock('../../stores/session', () => ({ useSession: () => ({ can: () => true }) }))
beforeEach(() => {
  setLocale('en')
  vi.mocked(api).mockReset()
})
const dpd = {
  carrier: 'DPD',
  status: 'credentials_configured',
  credentialsConfigured: true,
  environmentEnabled: true,
  enabled: false,
  liveApproved: false,
  capabilities: ['authentication', 'locations'],
  checkedAt: null,
  operation: null,
}
it('keeps checks disabled until explicitly enabled and never checks on load', async () => {
  vi.mocked(api).mockResolvedValue({ items: [dpd] })
  const wrapper = mount(SandboxManager)
  await flushPromises()
  expect(api).toHaveBeenCalledTimes(1)
  expect(
    wrapper.get('button[aria-label="Test authentication DPD"]').attributes('disabled'),
  ).toBeDefined()
  vi.mocked(api)
    .mockResolvedValueOnce({ updated: true })
    .mockResolvedValueOnce({ items: [{ ...dpd, enabled: true }] })
  await wrapper.get('button[aria-label="Enable sandbox DPD"]').trigger('click')
  await flushPromises()
  expect(api).toHaveBeenCalledWith('/admin/carrier-sandboxes/DPD', 'PATCH', { enabled: true })
  expect(
    wrapper.get('button[aria-label="Test authentication DPD"]').attributes('disabled'),
  ).toBeUndefined()
  expect(wrapper.text()).toContain('Live integration not approved')
  wrapper.unmount()
})
it('reports the actual explicit check result without suggesting live approval', async () => {
  vi.mocked(api).mockResolvedValueOnce({ items: [{ ...dpd, enabled: true }] })
  const wrapper = mount(SandboxManager)
  await flushPromises()
  vi.mocked(api)
    .mockResolvedValueOnce({
      carrier: 'DPD',
      operation: 'authentication',
      status: 'authentication_failed',
      checkedAt: '2026-10-29T12:00:00Z',
    })
    .mockResolvedValueOnce({ items: [{ ...dpd, enabled: true, status: 'authentication_failed' }] })
  await wrapper.get('button[aria-label="Test authentication DPD"]').trigger('click')
  await flushPromises()
  expect(api).toHaveBeenCalledWith('/admin/carrier-sandboxes/DPD/check', 'POST', {
    operation: 'authentication',
  })
  expect(wrapper.get('[role=status]').text()).toContain('Authentication failed')
  wrapper.unmount()
})
it('does not offer GLS checks without an official contract', async () => {
  vi.mocked(api).mockResolvedValue({
    items: [
      {
        ...dpd,
        carrier: 'GLS',
        status: 'specification_unavailable',
        capabilities: [],
        credentialsConfigured: false,
        environmentEnabled: false,
      },
    ],
  })
  const wrapper = mount(SandboxManager)
  await flushPromises()
  expect(wrapper.text()).toContain('Official specification required')
  expect(wrapper.find('button').exists()).toBe(false)
  wrapper.unmount()
})
