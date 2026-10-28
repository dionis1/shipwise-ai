import { afterEach, describe, expect, it, vi } from 'vitest'
import { api, ApiError } from './client'
import { formatMoney } from './format'
afterEach(() => vi.unstubAllGlobals())
describe('API transport', () => {
  it('fetches CSRF and uses same-origin credentials for writes', async () => {
    const fetcher = vi
      .fn()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf' })))
      .mockResolvedValueOnce(new Response(JSON.stringify({ message: 'ok' })))
    vi.stubGlobal('fetch', fetcher)
    await api('/auth/logout', 'POST', {})
    expect(fetcher).toHaveBeenLastCalledWith(
      '/api/v1/auth/logout',
      expect.objectContaining({
        credentials: 'same-origin',
        headers: expect.objectContaining({ 'X-CSRF-Token': 'csrf' }),
      }),
    )
  })
  it('turns Problem Details into a typed error', async () => {
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValue(
          new Response(JSON.stringify({ detail: 'Access denied' }), { status: 403 }),
        ),
    )
    await expect(api('/admin/staff')).rejects.toBeInstanceOf(ApiError)
  })
  it('does not send a write when CSRF initialization fails', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response('{}', { status: 503 }))
    vi.stubGlobal('fetch', fetcher)
    await expect(api('/me', 'PATCH', {})).rejects.toThrow('Unable to initialize')
    expect(fetcher).toHaveBeenCalledTimes(1)
  })
  it('formats minor units according to the currency', () => {
    expect(formatMoney(1234, 'EUR', 'en-GB')).toBe('€12.34')
    expect(formatMoney(1234, 'JPY', 'en-GB')).toBe('JP¥1,234')
  })
})
