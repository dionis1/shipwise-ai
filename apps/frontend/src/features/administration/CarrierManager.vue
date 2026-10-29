<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, errorMessage } from '../../api/client'
import type { components } from '../../api/generated'
import { useSession } from '../../stores/session'
import { formatDate, formatNumber } from '../../i18n'
import SandboxManager from './SandboxManager.vue'
import AppCard from '../../components/AppCard.vue'
import AppButton from '../../components/AppButton.vue'
import AppInput from '../../components/AppInput.vue'
import AppSelect from '../../components/AppSelect.vue'
import AppBadge from '../../components/AppBadge.vue'
import AppModal from '../../components/AppModal.vue'
import FeedbackBox from '../../components/FeedbackBox.vue'
const { t } = useI18n(),
  session = useSession()
const catalog = ref<components['schemas']['AdminCarriers'] | null>(null),
  tariffs = ref<components['schemas']['VersionRecord'][]>([]),
  pricing = ref<components['schemas']['VersionRecord'][]>([])
const diagnostics = ref<components['schemas']['QuoteDiagnostics']['items']>([])
const busy = ref(false),
  loading = ref(false),
  failure = ref<unknown>(),
  saved = ref(false),
  editor = ref(''),
  confirm = ref(false),
  pending = ref<{ code: string; enabled: boolean; serviceCode?: string }>(),
  invalid = ref(false)
const form = reactive({
  serviceCode: 'DEV_STANDARD',
  version: '',
  baseMinor: '400',
  perKgMinor: '50',
  surchargeMinor: '0',
  fixedMarginMinor: '100',
  minimumMarginMinor: '150',
  marginBasisPoints: '1000',
  feeMinor: '25',
  taxBasisPoints: '1900',
  effectiveFrom: new Date().toISOString().slice(0, 19) + 'Z',
})
const error = computed(() =>
  invalid.value ? t('quote.adminInteger') : failure.value ? errorMessage(failure.value) : '',
)
async function load() {
  loading.value = true
  failure.value = undefined
  try {
    catalog.value = await api('/admin/carriers')
    if (session.can('quotes.diagnostics'))
      diagnostics.value = (
        await api<components['schemas']['QuoteDiagnostics']>('/admin/quote-diagnostics')
      ).items
    if (session.can('tariffs.manage'))
      tariffs.value = (await api<components['schemas']['Versions']>('/admin/tariffs')).items
    if (session.can('pricing.manage'))
      pricing.value = (await api<components['schemas']['Versions']>('/admin/pricing-rules')).items
  } catch (e) {
    failure.value = e
  } finally {
    loading.value = false
  }
}
function change(code: string, enabled: boolean, serviceCode?: string) {
  pending.value = { code, enabled, serviceCode }
  confirm.value = true
  saved.value = false
}
async function apply() {
  if (!pending.value) return
  busy.value = true
  try {
    await api('/admin/carriers/' + pending.value.code, 'PATCH', {
      enabled: pending.value.enabled,
      ...(pending.value.serviceCode ? { serviceCode: pending.value.serviceCode } : {}),
    })
    confirm.value = false
    await load()
    saved.value = true
  } catch (e) {
    failure.value = e
  } finally {
    busy.value = false
  }
}
function start(mode: string) {
  if (mode === 'tariff' && !form.serviceCode) form.serviceCode = 'DEV_STANDARD'
  editor.value = mode
  form.version = ''
  invalid.value = false
  saved.value = false
}
async function save() {
  invalid.value = false
  failure.value = undefined
  const keys =
    editor.value === 'tariff'
      ? (['baseMinor', 'perKgMinor', 'surchargeMinor'] as const)
      : ([
          'fixedMarginMinor',
          'minimumMarginMinor',
          'marginBasisPoints',
          'feeMinor',
          'taxBasisPoints',
        ] as const)
  const values: Record<string, number> = {}
  for (const key of keys) {
    if (
      !/^\d{1,7}$/.test(form[key]) ||
      Number(form[key]) > (key.includes('BasisPoints') ? 10000 : 1000000)
    ) {
      invalid.value = true
      return
    }
    values[key] = Number(form[key])
  }
  if (!/^[a-zA-Z0-9._-]{1,80}$/.test(form.version)) {
    invalid.value = true
    return
  }
  busy.value = true
  try {
    await api(editor.value === 'tariff' ? '/admin/tariffs' : '/admin/pricing-rules', 'POST', {
      serviceCode: form.serviceCode || null,
      version: form.version,
      currency: 'EUR',
      ...values,
      ...(editor.value === 'tariff' ? { effectiveFrom: form.effectiveFrom } : {}),
    })
    editor.value = ''
    await load()
    saved.value = true
  } catch (e) {
    failure.value = e
  } finally {
    busy.value = false
  }
}
onMounted(load)
</script>
<template>
  <div class="carrier-catalog">
    <p class="feedback feedback-info">{{ t('quote.development') }}</p>
    <FeedbackBox
      :error="error"
      :loading="busy || loading"
      :success="saved ? t('quote.saved') : ''"
    />
    <SandboxManager />
    <AppCard :title="t('quote.adminTitle')">
      <div v-for="carrier in catalog?.carriers" :key="carrier.code" class="carrier-row">
        <div>
          <h3>{{ carrier.code === 'DEV_POST' ? t('quote.devCarrier') : carrier.name }}</h3>
          <p>
            {{ t('quote.configuration') }}:
            {{ t('quote.' + (carrier.mode === 'unconfigured' ? 'unconfigured' : 'devSource')) }}
          </p>
        </div>
        <div class="button-row">
          <AppBadge :tone="carrier.enabled ? 'success' : 'neutral'">
            {{ t('quote.' + (carrier.enabled ? 'enabled' : 'disabled')) }}
          </AppBadge>
          <AppButton
            v-if="session.can('carriers.configure')"
            variant="secondary"
            :disabled="busy || loading || carrier.mode === 'unconfigured'"
            :aria-label="t('quote.toggle') + ' ' + carrier.code"
            @click="change(carrier.code, !carrier.enabled)"
          >
            {{ t('quote.toggle') }}
          </AppButton>
        </div>
      </div>
    </AppCard>
    <AppCard :title="t('quote.service')">
      <div v-for="service in catalog?.services" :key="service.code" class="carrier-row">
        <div>
          <h3>{{ t('quote.' + service.code) }}</h3>
          <p>
            {{
              t('quote.limits', {
                weight: formatNumber(service.rules.maxWeightGrams),
                dimensions: service.rules.maxDimensionsMm.join(' × '),
              })
            }}
          </p>
          <p>{{ service.rules.routes.join(', ') }}</p>
          <p>{{ t('quote.version') }}: {{ service.tariff_version || '—' }}</p>
        </div>
        <div class="button-row">
          <AppBadge :tone="service.enabled && service.carrierEnabled ? 'success' : 'neutral'">
            {{ t('quote.' + (service.enabled && service.carrierEnabled ? 'enabled' : 'disabled')) }}
          </AppBadge>
          <AppButton
            v-if="session.can('carriers.configure')"
            variant="secondary"
            :disabled="busy || loading"
            :aria-label="t('quote.toggle') + ' ' + t('quote.' + service.code)"
            @click="change(service.carrier_code, !service.enabled, service.code)"
          >
            {{ t('quote.toggle') }}
          </AppButton>
        </div>
      </div>
    </AppCard>
    <AppCard v-if="session.can('tariffs.manage')" :title="t('quote.tariffs')">
      <AppButton :disabled="busy || loading" @click="start('tariff')">
        {{ t('quote.importTariff') }}
      </AppButton>
      <div v-for="item in tariffs" :key="item.id" class="carrier-row">
        <div>
          <strong>{{ item.version }}</strong>
          <p>
            {{ item.service_code }} · {{ t('quote.effective') }}:
            {{ item.effective_from ? formatDate(item.effective_from) : '—' }}
          </p>
          <code>{{ JSON.stringify(item.configuration) }}</code>
        </div>
        <AppBadge>{{ t('quote.' + (item.active ? 'active' : 'inactive')) }}</AppBadge>
      </div>
    </AppCard>
    <AppCard v-if="session.can('pricing.manage')" :title="t('quote.pricing')">
      <AppButton :disabled="busy || loading" @click="start('pricing')">
        {{ t('quote.createPricing') }}
      </AppButton>
      <div v-for="item in pricing" :key="item.id" class="carrier-row">
        <div>
          <strong>{{ item.version }}</strong>
          <p>{{ item.service_code || t('quote.defaultRule') }}</p>
          <code>{{ JSON.stringify(item.configuration) }}</code>
        </div>
        <AppBadge>{{ t('quote.' + (item.active ? 'active' : 'inactive')) }}</AppBadge>
      </div>
    </AppCard>
    <AppModal v-model:open="confirm" :title="t('quote.confirm')" :busy="busy">
      <p v-if="pending">
        <strong>
          {{ pending.serviceCode ? t('quote.' + pending.serviceCode) : pending.code }}
        </strong>
        · {{ t('quote.' + (pending.enabled ? 'enabled' : 'disabled')) }}
      </p>
      <p>{{ t('quote.confirmBody') }}</p>
      <FeedbackBox :error="error" />
      <template #footer>
        <AppButton variant="secondary" :disabled="busy" @click="confirm = false">
          {{ t('common.cancel') }}
        </AppButton>
        <AppButton :busy="busy" @click="apply">{{ t('common.save') }}</AppButton>
      </template>
    </AppModal>
    <AppCard v-if="session.can('quotes.diagnostics')" :title="t('quote.diagnostics')">
      <div v-for="item in diagnostics" :key="item.status" class="carrier-row">
        <span>{{ t('quote.reasons.' + item.status) }}</span>
        <strong>{{ formatNumber(item.count) }}</strong>
      </div>
    </AppCard>
    <AppModal
      :open="!!editor"
      :title="t(editor === 'tariff' ? 'quote.importTariff' : 'quote.createPricing')"
      :busy="busy"
      @update:open="editor = ''"
    >
      <p class="feedback feedback-info">{{ t('quote.development') }}</p>
      <form id="version-form" novalidate @submit.prevent="save">
        <AppSelect v-model="form.serviceCode" :label="t('quote.service')">
          <option v-if="editor === 'pricing'" value="">{{ t('quote.defaultRule') }}</option>
          <option value="DEV_STANDARD">{{ t('quote.DEV_STANDARD') }}</option>
          <option value="DEV_EXPRESS">{{ t('quote.DEV_EXPRESS') }}</option>
        </AppSelect>
        <AppInput v-model="form.version" :label="t('quote.version')" required />
        <template v-if="editor === 'tariff'">
          <AppInput v-model="form.baseMinor" :label="t('quote.baseInput')" required />
          <AppInput v-model="form.perKgMinor" :label="t('quote.perKg')" required />
          <AppInput v-model="form.surchargeMinor" :label="t('quote.surchargeInput')" required />
          <AppInput v-model="form.effectiveFrom" :label="t('quote.effective')" required />
        </template>
        <template v-else>
          <AppInput v-model="form.fixedMarginMinor" :label="t('quote.fixedMargin')" required />
          <AppInput v-model="form.minimumMarginMinor" :label="t('quote.minimumMargin')" required />
          <AppInput v-model="form.marginBasisPoints" :label="t('quote.marginBps')" required />
          <AppInput v-model="form.feeMinor" :label="t('quote.feeInput')" required />
          <AppInput v-model="form.taxBasisPoints" :label="t('quote.taxBps')" required />
        </template>
        <FeedbackBox :error="error" />
      </form>
      <template #footer>
        <AppButton variant="secondary" :disabled="busy" @click="editor = ''">
          {{ t('common.cancel') }}
        </AppButton>
        <AppButton type="submit" form="version-form" :busy="busy">{{ t('common.save') }}</AppButton>
      </template>
    </AppModal>
  </div>
</template>
