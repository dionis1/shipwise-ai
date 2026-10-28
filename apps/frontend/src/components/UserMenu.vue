<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useSession } from '../stores/session'
import { translatedCode } from '../i18n'
import { errorMessage } from '../api/client'
import AppIcon from './AppIcon.vue'
const menu = ref<HTMLDetailsElement>()
function dismiss(event: Event) {
  if (menu.value && !menu.value.contains(event.target as Node)) menu.value.open = false
}
function escape() {
  if (menu.value) {
    menu.value.open = false
    menu.value.querySelector('summary')?.focus()
  }
}
onMounted(() => document.addEventListener('pointerdown', dismiss))
onBeforeUnmount(() => document.removeEventListener('pointerdown', dismiss))
const session = useSession(),
  router = useRouter(),
  { t } = useI18n(),
  busy = ref(false),
  error = ref('')
async function logout() {
  busy.value = true
  error.value = ''
  try {
    await session.logout()
    await router.push('/login')
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <details ref="menu" class="user-menu" @keydown.esc.prevent="escape">
    <summary :aria-label="t('common.accountMenu')">
      <span class="user-avatar">{{ session.user?.name.slice(0, 1) }}</span>
      <span class="user-info">
        <strong>{{ session.user?.name }}</strong>
        <small>
          {{ session.user?.roles.map((role) => translatedCode('roles', role)).join(', ') }}
        </small>
      </span>
      <AppIcon name="down" :size="16" />
    </summary>
    <div class="user-menu-panel">
      <strong>{{ session.user?.name }}</strong>
      <p>{{ session.user?.email }}</p>
      <RouterLink v-if="session.user?.kind === 'customer'" to="/app/settings">
        <AppIcon name="settings" />
        {{ t('navigation.settings') }}
      </RouterLink>
      <button :disabled="busy" type="button" @click="logout">
        <AppIcon name="logout" />
        {{ t('auth.logout') }}
      </button>
      <p v-if="error" role="alert" class="field-error">{{ error }}</p>
    </div>
  </details>
</template>
