<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import AppIcon from './AppIcon.vue'
import AppBadge from './AppBadge.vue'
defineProps<{ title: string; description?: string; icon?: string }>()
const { t } = useI18n()
</script>
<template>
  <header class="page-header">
    <span v-if="icon" class="page-icon"><AppIcon :name="icon" :size="27" /></span>
    <div class="page-header-copy">
      <h1>{{ title }}</h1>
      <p v-if="description">{{ description }}</p>
    </div>
    <AppBadge tone="success" class="phase-badge">
      {{ t('common.phase') }}
      <span aria-hidden="true">·</span>
      {{ t('common.foundation') }}
    </AppBadge>
    <slot />
  </header>
</template>
