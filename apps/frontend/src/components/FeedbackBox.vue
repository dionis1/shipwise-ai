<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import AppIcon from './AppIcon.vue'
defineProps<{ error?: string; success?: string; loading?: boolean }>()
const { t } = useI18n()
</script>
<template>
  <p v-if="loading" role="status" class="feedback feedback-info">
    <AppIcon name="loading" class="spin" />{{ t('common.loading') }}
  </p>
  <p v-if="error" role="alert" class="feedback feedback-error">
    <AppIcon name="warning" />{{ error }}
  </p>
  <p v-if="success && !loading" role="status" class="feedback feedback-success">
    <AppIcon name="check" />{{ success }}
  </p>
</template>
