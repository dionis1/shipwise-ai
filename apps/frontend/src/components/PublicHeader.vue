<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useSession } from '../stores/session'
import { landingFor } from '../router'
import BrandMark from './BrandMark.vue'
import LanguageSwitcher from './LanguageSwitcher.vue'
import UserMenu from './UserMenu.vue'
const { t } = useI18n(),
  session = useSession()
defineProps<{ customer?: boolean }>()
</script>
<template>
  <a href="#main-content" class="skip-link">{{ t('common.skip') }}</a>
  <header class="site-header">
    <BrandMark />
    <nav :aria-label="t('common.navigation')" class="site-nav">
      <template v-if="customer">
        <RouterLink to="/app" :class="{ current: $route.path === '/app' }">
          {{ t('navigation.overview') }}
        </RouterLink>
        <RouterLink to="/app/profile">{{ t('navigation.profile') }}</RouterLink>
        <RouterLink to="/app/settings">{{ t('navigation.settings') }}</RouterLink>
      </template>
      <RouterLink
        v-else-if="session.user"
        :to="landingFor(session.user.permissions, session.user.kind)"
      >
        {{ t('common.workspace') }}
      </RouterLink>
      <template v-else>
        <RouterLink class="sign-in-link" to="/login">{{ t('auth.login') }}</RouterLink>
        <RouterLink class="app-button button-primary register-link" to="/register">
          {{ t('auth.register') }}
        </RouterLink>
      </template>
    </nav>
    <div class="header-actions">
      <LanguageSwitcher />
      <UserMenu v-if="session.user" />
    </div>
  </header>
</template>
