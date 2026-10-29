import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import ParcelShopsView from './ParcelShopsView.vue'
import { api } from '../api/client'
import { setLocale } from '../i18n'
vi.mock('../api/client', async (original) => ({
  ...(await original<typeof import('../api/client')>()),
  api: vi.fn(),
}))
beforeEach(() => {
  setActivePinia(createPinia())
  setLocale('en')
  vi.mocked(api).mockReset()
})
function view() {
  return mount(ParcelShopsView, {
    global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
  })
}
async function search(wrapper: ReturnType<typeof view>) {
  await wrapper.findAll('input')[0].setValue('10115')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}
describe('parcel-shop discovery', () => {
  it('does not fetch on load or invalid search', async () => {
    const wrapper = view()
    await wrapper.get('form').trigger('submit')
    expect(api).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Enter a postal code')
    wrapper.unmount()
  })
  it('shows honest unavailable states without fake shops', async () => {
    vi.mocked(api).mockResolvedValue({
      items: [],
      outcomes: [
        { carrier: 'DPD', status: 'not_configured' },
        { carrier: 'GLS', status: 'specification_unavailable' },
      ],
    })
    const wrapper = view()
    await search(wrapper)
    expect(wrapper.text()).toContain('Not configured')
    expect(wrapper.text()).toContain('Official specification required')
    expect(wrapper.find('.parcel-shop-list').exists()).toBe(false)
    wrapper.unmount()
  })
  it('renders supplied fields and clears stale results after changing search', async () => {
    vi.mocked(api).mockResolvedValue({
      items: [
        {
          carrier: 'DPD',
          id: 'synthetic-1',
          name: 'Synthetic Test Shop',
          address: null,
          postalCode: null,
          city: null,
          country: null,
          latitude: null,
          longitude: null,
          services: [],
          source: 'dpd_sandbox',
          retrievedAt: '2026-10-29T12:00:00Z',
        },
      ],
      outcomes: [{ carrier: 'DPD', status: 'available' }],
    })
    const wrapper = view()
    await search(wrapper)
    expect(api).toHaveBeenCalledWith(
      '/parcel-shops?country=DE&postalCode=10115&carrier=all&limit=10',
    )
    expect(wrapper.text()).toContain('Synthetic Test Shop')
    expect(wrapper.text()).not.toContain('Coordinates:')
    expect(wrapper.text()).not.toContain('undefined')
    expect(wrapper.text()).not.toContain('€')
    await wrapper.findAll('input')[0].setValue('75001')
    expect(wrapper.find('.parcel-shop-list').exists()).toBe(false)
    wrapper.unmount()
  })
  it('distinguishes available empty results', async () => {
    vi.mocked(api).mockResolvedValue({
      items: [],
      outcomes: [{ carrier: 'DPD', status: 'available' }],
    })
    const wrapper = view()
    await search(wrapper)
    expect(wrapper.text()).toContain('No parcel shops found')
    wrapper.unmount()
  })
  it('shows loading and failure without interpreting provider data', async () => {
    let reject!: (e: Error) => void
    vi.mocked(api).mockReturnValue(
      new Promise((_, r) => {
        reject = r
      }),
    )
    const wrapper = view()
    await wrapper.findAll('input')[0].setValue('10115')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.get('button[type=submit]').attributes('disabled')).toBeDefined()
    reject(new Error('private upstream response'))
    await flushPromises()
    expect(wrapper.text()).not.toContain('private upstream')
    expect(wrapper.find('[role=alert]').exists()).toBe(true)
    wrapper.unmount()
  })
  for (const [locale, title] of [
    ['de', 'Paketshops'],
    ['en', 'Parcel shops'],
    ['fr', 'Points relais'],
  ] as const)
    it('supports ' + locale, () => {
      setLocale(locale)
      const wrapper = view()
      expect(wrapper.get('h1').text()).toBe(title)
      wrapper.unmount()
    })
})
