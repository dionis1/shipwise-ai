import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import AuditTable from './AuditTable.vue'
import { api } from '../../api/client'
import { setLocale } from '../../i18n'
vi.mock('../../api/client', async (original) => ({
  ...(await original<typeof import('../../api/client')>()),
  api: vi.fn(),
}))
const event = {
  id: 'event',
  actor_id: 'actor',
  target_id: 'target',
  action: 'auth.login',
  context: {},
  correlation_id: 'correlation',
  created_at: '2026-10-28T12:00:00Z',
}
const empty = { items: [], page: 1, pageSize: 20, total: 0 }
beforeEach(() => {
  setLocale('en')
  vi.mocked(api).mockReset()
})
afterEach(() => {
  document.body.innerHTML = ''
})
describe('audit details and result consistency', () => {
  it('clears unrelated detail selection when filters change and results are empty', async () => {
    vi.mocked(api)
      .mockResolvedValueOnce({ ...empty, items: [event], total: 1 })
      .mockResolvedValueOnce(empty)
    const wrapper = mount(AuditTable, { attachTo: document.body })
    await flushPromises()
    await wrapper.get('td button').trigger('click')
    await flushPromises()
    expect(document.querySelector('dialog')?.open).toBe(true)
    await wrapper.get('input[type="text"]').setValue('unmatched-account')
    await flushPromises()
    expect(document.querySelector('dialog')?.open).toBe(false)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('No matching events.')
    expect(document.querySelector('dialog')?.open).toBe(false)
    expect(wrapper.find('td').exists()).toBe(false)
    wrapper.unmount()
  })
  it('ignores an older response when a newer search has finished', async () => {
    let resolveFirst: (data: unknown) => void = () => {}
    vi.mocked(api)
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveFirst = resolve
        }),
      )
      .mockResolvedValueOnce(empty)
    const wrapper = mount(AuditTable, { attachTo: document.body })
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    resolveFirst({ ...empty, items: [event], total: 1 })
    await flushPromises()
    expect(wrapper.text()).toContain('No matching events.')
    expect(wrapper.find('td').exists()).toBe(false)
    wrapper.unmount()
  })
  it('does not invent IP addresses or geographic metadata', async () => {
    vi.mocked(api).mockResolvedValue({ ...empty, items: [event], total: 1 })
    const wrapper = mount(AuditTable, { attachTo: document.body })
    await flushPromises()
    await wrapper.get('td button').trigger('click')
    await flushPromises()
    const detail = document.querySelector('dialog')!.textContent!
    expect(detail).toContain('No additional metadata')
    expect(detail).not.toContain('IP address')
    expect(detail).not.toContain('Location')
    wrapper.unmount()
  })
})
