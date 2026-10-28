import { createI18n } from 'vue-i18n'
import en from './locales/en.json'
import de from './locales/de.json'
import fr from './locales/fr.json'
export const locales = ['en', 'de', 'fr'] as const
export type Locale = (typeof locales)[number]
const preferenceKey = 'shipwise.locale'
export const isLocale = (value: unknown): value is Locale => locales.includes(value as Locale)
export function initialLocale(storage?: Pick<Storage, 'getItem'>, browserLanguage = 'en'): Locale {
  try {
    const saved = storage?.getItem(preferenceKey)
    if (isLocale(saved)) return saved
  } catch {
    /* Preferences remain usable when storage is unavailable. */
  }
  const detected = browserLanguage.toLowerCase().split('-')[0]
  return isLocale(detected) ? detected : 'en'
}
function preferenceStorage(): Storage | undefined {
  try {
    return window.localStorage
  } catch {
    return undefined
  }
}
export const i18n = createI18n({
  legacy: false,
  locale: initialLocale(
    preferenceStorage(),
    typeof navigator === 'undefined' ? 'en' : navigator.language,
  ),
  fallbackLocale: 'en',
  messages: { en, de, fr },
})
export function setLocale(locale: Locale) {
  i18n.global.locale.value = locale
  if (typeof document !== 'undefined') document.documentElement.lang = locale
  try {
    localStorage.setItem(preferenceKey, locale)
  } catch {
    /* A blocked preference store must never block account access. */
  }
}
export function translatedCode(group: string, code: string) {
  const key = group + '.' + code.replaceAll('.', '_')
  return i18n.global.te(key) ? i18n.global.t(key) : code
}
export function formatDate(value: string) {
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? '—'
    : new Intl.DateTimeFormat(i18n.global.locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'UTC',
      }).format(date)
}
export function formatNumber(value: number | string) {
  return new Intl.NumberFormat(i18n.global.locale.value).format(Number(value))
}
setLocale(i18n.global.locale.value as Locale)
