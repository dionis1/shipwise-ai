import { afterEach, describe, expect, it } from 'vitest'
import { i18n, initialLocale, setLocale, formatDate, formatNumber } from './index'
import { validateFields } from './validation'
import en from './locales/en.json'
import de from './locales/de.json'
import fr from './locales/fr.json'
function keys(value: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(value)
    .flatMap(([key, item]) =>
      typeof item === 'object' && item
        ? keys(item as Record<string, unknown>, prefix + key + '.')
        : [prefix + key],
    )
    .sort()
}
afterEach(() => {
  i18n.global.setLocaleMessage('fr', fr)
  setLocale('en')
  localStorage.clear()
})
describe('internationalization', () => {
  it('has identical nonempty keys in EN DE and FR', () => {
    expect(keys(de)).toEqual(keys(en))
    expect(keys(fr)).toEqual(keys(en))
    expect(keys(en).length).toBeGreaterThan(300)
  })
  it('detects supported regional browser languages on first visit', () => {
    expect(initialLocale(undefined, 'de-CH')).toBe('de')
    expect(initialLocale(undefined, 'fr-CA')).toBe('fr')
    expect(initialLocale(undefined, 'it-IT')).toBe('en')
  })
  it('prefers a saved locale over browser detection and ignores corrupt values', () => {
    expect(initialLocale({ getItem: () => 'fr' }, 'de-DE')).toBe('fr')
    expect(initialLocale({ getItem: () => 'broken' }, 'de-DE')).toBe('de')
  })
  it('supports browsers where preference storage is blocked', () => {
    expect(
      initialLocale(
        {
          getItem: () => {
            throw new Error('blocked')
          },
        },
        'fr',
      ),
    ).toBe('fr')
  })
  it('updates the document language and stores only a non-sensitive preference', () => {
    setLocale('de')
    expect(document.documentElement.lang).toBe('de')
    expect(localStorage.getItem('shipwise.locale')).toBe('de')
    expect(localStorage.length).toBe(1)
  })
  it('falls back to English for missing messages', () => {
    const copy = structuredClone(fr)
    delete (copy.common as Record<string, unknown>).continue
    i18n.global.setLocaleMessage('fr', copy)
    setLocale('fr')
    expect(i18n.global.t('common.continue')).toBe('Continue')
  })
  it('formats dates in the selected locale while keeping UTC semantics', () => {
    setLocale('de')
    expect(formatDate('2026-10-28T12:30:00Z')).toContain('28.10.2026')
    expect(formatNumber(1234.5)).toBe('1.234,5')
    setLocale('fr')
    expect(formatNumber(1234.5)).toMatch(/1.234,5/)
  })
  it('reports language-neutral validation codes for translated form feedback', () => {
    expect(
      validateFields(
        { email: 'bad', name: 'x', password: 'short' },
        { email: 'email', name: 'name', password: 'password' },
      ),
    ).toEqual({ email: 'email_invalid', name: 'name_invalid', password: 'password_invalid' })
  })
})
