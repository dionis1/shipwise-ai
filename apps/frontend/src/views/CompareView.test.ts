import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import CompareView from './CompareView.vue'
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
afterEach(() => {
  vi.restoreAllMocks()
})
function view() {
  return mount(CompareView, { global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } } })
}
async function fill(wrapper: ReturnType<typeof view>) {
  const inputs = wrapper.findAll('input')
  for (const [index, value] of ['10115', '10115', '1.001', '30', '20', '10'].entries())
    await inputs[index].setValue(value)
}
describe('shipment comparison', () => {
  it('rejects invalid measurements before calling the API', async () => {
    const wrapper = view()
    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Enter positive measurements')
    expect(api).not.toHaveBeenCalled()
    wrapper.unmount()
  })
  it('sends canonical units and displays partial failure and exact backend prices', async () => {
    vi.mocked(api).mockResolvedValue({
      id: 'q',
      expired: false,
      expiresAt: '2099-01-01T00:00:00Z',
      offers: [
        {
          id: 'o',
          serviceCode: 'DEV_STANDARD',
          carrierName: 'Development',
          price: { totalMinor: 803, currency: 'EUR' },
          options: ['drop_off'],
          expiresAt: '2099-01-01T00:00:00Z',
        },
      ],
      outcomes: [{ serviceCode: 'DEV_EXPRESS', status: 'timeout', reasons: [] }],
    })
    const wrapper = view()
    await fill(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(api).toHaveBeenCalledWith(
      '/quotes',
      'POST',
      expect.objectContaining({ weightGrams: 1001, lengthMm: 300, quantity: 1 }),
      expect.objectContaining({ 'Idempotency-Key': expect.any(String) }),
    )
    expect(wrapper.get('[data-testid="quote-price"]').text()).toBe('€8.03')
    expect(wrapper.text()).toContain('Provider timed out')
    wrapper.unmount()
  })
  it('shows loading and an honest no-offer state', async () => {
    let finish: (value: unknown) => void = () => {}
    vi.mocked(api).mockReturnValue(
      new Promise((resolve) => {
        finish = resolve
      }),
    )
    const wrapper = view()
    await fill(wrapper)
    await wrapper.get('form').trigger('submit')
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
    finish({
      id: 'q',
      expired: false,
      expiresAt: '2099-01-01T00:00:00Z',
      offers: [],
      outcomes: [
        { serviceCode: 'DEV_STANDARD', status: 'ineligible', reasons: ['route_unsupported'] },
      ],
    })
    await flushPromises()
    expect(wrapper.text()).toContain('No eligible offers')
    expect(wrapper.text()).toContain('Route not supported')
    wrapper.unmount()
  })
  it('sorts by backend price and by service without inventing speed estimates', async () => {
    const base = {
      id: 'o',
      carrierName: 'Development',
      options: ['drop_off'],
      expiresAt: '2099-01-01T00:00:00Z',
    }
    vi.mocked(api).mockResolvedValue({
      id: 'q',
      expired: false,
      expiresAt: base.expiresAt,
      outcomes: [],
      offers: [
        {
          ...base,
          id: 'express',
          serviceCode: 'DEV_EXPRESS',
          price: { totalMinor: 1000, currency: 'EUR' },
        },
        {
          ...base,
          id: 'standard',
          serviceCode: 'DEV_STANDARD',
          price: { totalMinor: 500, currency: 'EUR' },
        },
      ],
    })
    const wrapper = view()
    await fill(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.findAll('[data-testid="quote-price"]')[0].text()).toBe('€5.00')
    await wrapper.findAll('select').at(-1)!.setValue('carrier')
    expect(wrapper.findAll('[data-testid="quote-price"]')[0].text()).toBe('€10.00')
    expect(wrapper.text()).not.toContain('Fastest delivery')
    wrapper.unmount()
  })
})
