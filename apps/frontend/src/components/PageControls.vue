<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '../i18n'
import AppButton from './AppButton.vue'
const props = defineProps<{ page: number; total: number; pageSize: number; busy?: boolean }>()
defineEmits<{ change: [page: number] }>()
const { t } = useI18n(),
  pages = computed(() => Math.max(1, Math.ceil(props.total / props.pageSize)))
</script>
<template>
  <nav class="pagination" :aria-label="t('common.pagination')">
    <span>
      {{ t('common.results', { count: formatNumber(total) }, total) }}
      <small>
        {{ t('common.page', { page: formatNumber(page), pages: formatNumber(pages) }) }}
      </small>
    </span>
    <div class="button-row">
      <AppButton
        variant="secondary"
        :disabled="busy || page <= 1"
        icon="left"
        @click="$emit('change', page - 1)"
      >
        {{ t('common.previous') }}
      </AppButton>
      <AppButton
        variant="secondary"
        :disabled="busy || page >= pages"
        @click="$emit('change', page + 1)"
      >
        {{ t('common.next') }}
      </AppButton>
    </div>
  </nav>
</template>
