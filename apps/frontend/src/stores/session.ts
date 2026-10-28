import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, ApiError, type Profile } from '../api/client'
export const useSession = defineStore('session', () => {
  const user = ref<Profile | null>(null)
  const initialized = ref(false)
  const can = (permission: string) => user.value?.permissions.includes(permission) ?? false
  async function refresh() {
    try {
      user.value = await api<Profile>('/auth/me')
    } catch (error) {
      if (!(error instanceof ApiError) || error.status !== 401) throw error
      user.value = null
    } finally {
      initialized.value = true
    }
  }
  async function login(email: string, password: string) {
    user.value = await api<Profile>('/auth/login', 'POST', { email, password })
    initialized.value = true
  }
  async function logout() {
    await api('/auth/logout', 'POST', {})
    user.value = null
    initialized.value = true
  }
  return { user, initialized, can, refresh, login, logout }
})
