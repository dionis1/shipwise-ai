import { defineStore } from 'pinia'
import { ref } from 'vue'
import { isLocale, setLocale, type Locale } from '../i18n'
import { api, ApiError, type Profile } from '../api/client'
export const useSession = defineStore('session', () => {
  const user = ref<Profile | null>(null)
  const initialized = ref(false)
  const can = (permission: string) => user.value?.permissions.includes(permission) ?? false
  async function refresh() {
    try {
      user.value = await api<Profile>('/auth/me')
      if (isLocale(user.value.preferredLocale)) setLocale(user.value.preferredLocale)
    } catch (error) {
      if (!(error instanceof ApiError) || error.status !== 401) throw error
      user.value = null
    } finally {
      initialized.value = true
    }
  }
  async function login(email: string, password: string) {
    user.value = await api<Profile>('/auth/login', 'POST', { email, password })
    if (isLocale(user.value.preferredLocale)) setLocale(user.value.preferredLocale)
    initialized.value = true
  }
  async function logout() {
    await api('/auth/logout', 'POST', {})
    user.value = null
    initialized.value = true
  }
  async function updateLocale(preferredLocale: Locale) {
    user.value = await api<Profile>('/me', 'PATCH', { preferredLocale })
  }
  return { user, initialized, can, refresh, login, logout, updateLocale }
})
