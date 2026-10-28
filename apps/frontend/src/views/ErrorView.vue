<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import PublicHeader from '../components/PublicHeader.vue'
import AppEmptyState from '../components/AppEmptyState.vue'
const { t } = useI18n()
</script>
<template>
  <PublicHeader />
  <main id="main-content" class="error-page">
    <AppEmptyState :title="t('error.title')" :description="t('error.description')" icon="lock">
      <RouterLink class="app-button button-primary" to="/login">
        {{ t('auth.backLogin') }}
      </RouterLink>
    </AppEmptyState>
  </main>
</template>
