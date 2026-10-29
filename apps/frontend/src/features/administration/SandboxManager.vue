<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, errorMessage } from '../../api/client'
import type { components } from '../../api/generated'
import { useSession } from '../../stores/session'
import { formatDate } from '../../i18n'
import AppCard from '../../components/AppCard.vue'
import AppButton from '../../components/AppButton.vue'
import AppBadge from '../../components/AppBadge.vue'
import FeedbackBox from '../../components/FeedbackBox.vue'
const { t } = useI18n(),
  session = useSession()
const items = ref<components['schemas']['SandboxIntegration'][]>([]),
  busy = ref(false),
  error = ref(''),
  checked = ref<components['schemas']['SandboxCheck']>()
async function load() {
  items.value = (
    await api<components['schemas']['SandboxIntegrations']>('/admin/carrier-sandboxes')
  ).items
}
async function act(
  carrier: string,
  operation: 'enable' | 'disable' | 'authentication' | 'validation',
) {
  busy.value = true
  error.value = ''
  checked.value = undefined
  try {
    if (operation === 'enable' || operation === 'disable')
      await api('/admin/carrier-sandboxes/' + carrier, 'PATCH', { enabled: operation === 'enable' })
    else
      checked.value = await api<components['schemas']['SandboxCheck']>(
        '/admin/carrier-sandboxes/' + carrier + '/check',
        'POST',
        { operation },
      )
    await load()
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
onMounted(async () => {
  try {
    await load()
  } catch (e) {
    error.value = errorMessage(e)
  }
})
</script>
<template>
  <AppCard :title="t('sandbox.title')">
    <p>{{ t('sandbox.boundary') }}</p>
    <p class="field-hint">{{ t('sandbox.configurationHint') }}</p>
    <FeedbackBox :error="error" :loading="busy" />
    <p v-if="checked" role="status">
      {{ checked.carrier }} · {{ t('sandbox.operation.' + checked.operation) }}:
      {{ t('sandbox.status.' + checked.status) }}
    </p>
    <div v-for="item in items" :key="item.carrier" class="carrier-row" data-testid="sandbox-row">
      <div>
        <h3>{{ item.carrier }}</h3>
        <p>{{ t('sandbox.status.' + item.status) }}</p>
        <p>{{ t('sandbox.liveNotApproved') }}</p>
        <p>
          {{ t('sandbox.environment') }}:
          {{ t(item.environmentEnabled ? 'quote.enabled' : 'quote.disabled') }} ·
          {{ t('sandbox.switch') }}: {{ t(item.enabled ? 'quote.enabled' : 'quote.disabled') }}
        </p>
        <p v-if="item.checkedAt && item.operation">
          {{ t('sandbox.operation.' + item.operation) }} · {{ formatDate(item.checkedAt) }}
        </p>
      </div>
      <div class="button-row">
        <AppBadge>{{ t('sandbox.status.' + item.status) }}</AppBadge>
        <template v-if="session.can('carriers.configure') && item.capabilities.length">
          <AppButton
            variant="secondary"
            :disabled="
              busy || (!item.enabled && (!item.environmentEnabled || !item.credentialsConfigured))
            "
            :aria-label="
              t(item.enabled ? 'sandbox.disable' : 'sandbox.enable') + ' ' + item.carrier
            "
            @click="act(item.carrier, item.enabled ? 'disable' : 'enable')"
          >
            {{ t(item.enabled ? 'sandbox.disable' : 'sandbox.enable') }}
          </AppButton>
          <AppButton
            v-for="operation in item.capabilities.filter((value) => value !== 'locations')"
            :key="operation"
            variant="secondary"
            :disabled="
              busy || !item.enabled || !item.environmentEnabled || !item.credentialsConfigured
            "
            :aria-label="t('sandbox.operation.' + operation) + ' ' + item.carrier"
            @click="act(item.carrier, operation as 'authentication' | 'validation')"
          >
            {{ t('sandbox.operation.' + operation) }}
          </AppButton>
        </template>
      </div>
    </div>
  </AppCard>
</template>
