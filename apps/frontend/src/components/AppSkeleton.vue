<script setup lang="ts">
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
</script>
<template>
  <div role="status" :aria-label="t('common.loading')" class="skeleton-stack">
    <span class="sr-only">{{ t('common.loading') }}</span>
    <div v-for="row in 3" :key="row" class="skeleton-row"></div>
  </div>
</template>
