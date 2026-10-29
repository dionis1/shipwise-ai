<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { components } from '../api/generated'
import { api, errorMessage, ApiError } from '../api/client'
import { formatDate } from '../i18n'
import { canonicalMeasurement } from '../features/quoting/measurements'
import PublicHeader from '../components/PublicHeader.vue'
import AppCard from '../components/AppCard.vue'
import AppInput from '../components/AppInput.vue'
import AppSelect from '../components/AppSelect.vue'
import AppButton from '../components/AppButton.vue'
import AppBadge from '../components/AppBadge.vue'
import AppModal from '../components/AppModal.vue'
import AppEmptyState from '../components/AppEmptyState.vue'
import FeedbackBox from '../components/FeedbackBox.vue'
type Quote = components['schemas']['Quote']
type Offer = components['schemas']['QuoteOffer']
const { t, locale } = useI18n()
const form = reactive({
  originCountry: 'DE',
  originPostalCode: '',
  destinationCountry: 'DE',
  destinationPostalCode: '',
  weight: '',
  length: '',
  width: '',
  height: '',
  handover: 'any',
  speed: 'any',
})
const quote = ref<Quote | null>(null),
  selected = ref<Offer | null>(null),
  open = ref(false),
  busy = ref(false),
  failure = ref<unknown>(),
  validation = ref(''),
  sort = ref('price'),
  now = ref(Date.now())
const timer = window.setInterval(() => {
  now.value = Date.now()
}, 1000)
onBeforeUnmount(() => window.clearInterval(timer))
const expired = computed(
  () =>
    !!quote.value &&
    (quote.value.expired || new Date(quote.value.expiresAt).getTime() <= now.value),
)
const offers = computed(() =>
  [...(quote.value?.offers || [])].sort((a, b) =>
    sort.value === 'price'
      ? a.price.totalMinor - b.price.totalMinor
      : a.carrierName.localeCompare(b.carrierName, locale.value) ||
        a.serviceCode.localeCompare(b.serviceCode),
  ),
)
const failures = computed(
  () =>
    quote.value?.outcomes.filter((outcome) => !['quoted', 'ineligible'].includes(outcome.status)) ||
    [],
)
const reasons = computed(() => [
  ...new Set(quote.value?.outcomes.flatMap((outcome) => outcome.reasons) || []),
])
const error = computed(() =>
  validation.value
    ? t('quote.' + validation.value)
    : failure.value
      ? errorMessage(failure.value)
      : '',
)
function money(offer: Offer, minor = offer.price.totalMinor) {
  return new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency: offer.price.currency,
  }).format(minor / 100)
}
async function submit() {
  failure.value = undefined
  validation.value = ''
  selected.value = null
  open.value = false
  const weightGrams = canonicalMeasurement(form.weight, 3, 70000),
    lengthMm = canonicalMeasurement(form.length, 1, 2000),
    widthMm = canonicalMeasurement(form.width, 1, 2000),
    heightMm = canonicalMeasurement(form.height, 1, 2000)
  if ([weightGrams, lengthMm, widthMm, heightMm].some((value) => value === null)) {
    validation.value = 'measureInvalid'
    return
  }
  if (
    ![form.originPostalCode, form.destinationPostalCode].every((value) => /^\d{5}$/.test(value))
  ) {
    validation.value = 'postalInvalid'
    return
  }
  busy.value = true
  quote.value = null
  try {
    quote.value = await api<Quote>(
      '/quotes',
      'POST',
      {
        originCountry: form.originCountry,
        originPostalCode: form.originPostalCode,
        destinationCountry: form.destinationCountry,
        destinationPostalCode: form.destinationPostalCode,
        weightGrams,
        lengthMm,
        widthMm,
        heightMm,
        quantity: 1,
        handover: form.handover,
        speed: form.speed,
      },
      {
        'Idempotency-Key': Array.from(window.crypto.getRandomValues(new Uint8Array(16)), (value) =>
          value.toString(16).padStart(2, '0'),
        ).join(''),
      },
    )
  } catch (e) {
    failure.value = e
  } finally {
    busy.value = false
  }
}
async function select(offer: Offer) {
  failure.value = undefined
  try {
    const result = await api<components['schemas']['QuoteSelection']>(
      '/quotes/' + quote.value!.id + '/offers/' + offer.id,
      'GET',
      undefined,
      quote.value?.accessToken ? { 'X-Quote-Token': quote.value.accessToken } : {},
    )
    selected.value = result.offer
    open.value = true
  } catch (e) {
    failure.value = e
    if (quote.value && e instanceof ApiError && e.code === 'quote_expired')
      quote.value.expired = true
  }
}
</script>
<template>
  <div class="public-page">
    <PublicHeader />
    <main id="main-content" class="content-width compare-page">
      <header class="section-heading">
        <span class="eyebrow">{{ t('quote.eyebrow') }}</span>
        <h1>{{ t('quote.title') }}</h1>
        <p>{{ t('quote.description') }}</p>
      </header>
      <p class="feedback feedback-info">{{ t('quote.development') }}</p>
      <div class="quote-layout">
        <AppCard :title="t('quote.stepOne')">
          <form novalidate @submit.prevent="submit">
            <div class="quote-form-grid">
              <AppSelect v-model="form.originCountry" :label="t('quote.originCountry')">
                <option value="DE">{{ t('quote.DE') }}</option>
                <option value="FR">{{ t('quote.FR') }}</option>
              </AppSelect>
              <AppInput
                v-model="form.originPostalCode"
                :label="t('quote.originPostal')"
                :hint="t('quote.postalExample')"
                required
                :maxlength="5"
              />
              <AppSelect v-model="form.destinationCountry" :label="t('quote.destinationCountry')">
                <option value="DE">{{ t('quote.DE') }}</option>
                <option value="FR">{{ t('quote.FR') }}</option>
              </AppSelect>
              <AppInput
                v-model="form.destinationPostalCode"
                :label="t('quote.destinationPostal')"
                :hint="t('quote.postalExample')"
                required
                :maxlength="5"
              />
              <AppInput
                v-model="form.weight"
                :label="t('quote.weight')"
                :hint="t('quote.weightHint')"
                required
              />
              <AppInput
                v-model="form.length"
                :label="t('quote.length')"
                :hint="t('quote.dimensionHint')"
                required
              />
              <AppInput v-model="form.width" :label="t('quote.width')" required />
              <AppInput v-model="form.height" :label="t('quote.height')" required />
              <AppSelect v-model="form.handover" :label="t('quote.handover')">
                <option value="any">{{ t('quote.any') }}</option>
                <option value="drop_off">{{ t('quote.drop_off') }}</option>
                <option value="pickup">{{ t('quote.pickup') }}</option>
              </AppSelect>
              <AppSelect v-model="form.speed" :label="t('quote.speed')">
                <option value="any">{{ t('quote.any') }}</option>
                <option value="standard">{{ t('quote.standard') }}</option>
                <option value="express">{{ t('quote.express') }}</option>
              </AppSelect>
            </div>
            <AppButton type="submit" :busy="busy" icon="search">{{ t('quote.search') }}</AppButton>
          </form>
        </AppCard>
        <section aria-live="polite" :aria-label="t('quote.stepTwo')">
          <FeedbackBox :error="error" :loading="busy" />
          <AppCard v-if="!quote && !busy">
            <AppEmptyState
              :title="t('quote.ready')"
              :description="t('quote.readyBody')"
              icon="carriers"
            />
          </AppCard>
          <template v-if="quote">
            <div class="quote-results-heading">
              <h2>{{ t('quote.stepTwo') }}</h2>
              <AppSelect v-model="sort" :label="t('quote.sort')">
                <option value="price">{{ t('quote.lowest') }}</option>
                <option value="carrier">{{ t('quote.carrier') }}</option>
              </AppSelect>
            </div>
            <p v-if="expired" role="alert" class="feedback feedback-warning">
              {{ t('quote.expired') }}
            </p>
            <p v-if="failures.length" class="feedback feedback-warning">{{ t('quote.partial') }}</p>
            <ul v-if="failures.length" class="quote-reasons">
              <li v-for="item in failures" :key="item.serviceCode">
                {{ t('quote.' + item.serviceCode) }}: {{ t('quote.reasons.' + item.status) }}
              </li>
            </ul>
            <AppCard v-if="!offers.length">
              <AppEmptyState
                :title="t('quote.empty')"
                :description="t('quote.emptyBody')"
                icon="carriers"
              />
              <ul class="quote-reasons">
                <li v-for="reason in reasons" :key="reason">{{ t('quote.reasons.' + reason) }}</li>
              </ul>
            </AppCard>
            <AppCard v-for="offer in offers" :key="offer.id" class="quote-offer">
              <div class="quote-offer-heading">
                <div>
                  <AppBadge>{{ t('quote.devSource') }}</AppBadge>
                  <h3>{{ t('quote.' + offer.serviceCode) }}</h3>
                  <p>{{ t('quote.devCarrier') }}</p>
                </div>
                <strong class="quote-price" data-testid="quote-price">{{ money(offer) }}</strong>
              </div>
              <p>{{ t('quote.syntheticTax') }}</p>
              <p>
                {{ t('quote.validUntil', { date: formatDate(offer.expiresAt) }) }} ·
                {{ t('common.utc') }}
              </p>
              <div class="button-row">
                <AppBadge v-for="option in offer.options" :key="option">
                  {{ t('quote.' + option) }}
                </AppBadge>
              </div>
              <AppButton :disabled="expired" @click="select(offer)">
                {{ t('quote.details') }}
              </AppButton>
            </AppCard>
          </template>
        </section>
      </div>
      <AppModal v-model:open="open" :title="t('quote.details')" drawer>
        <template v-if="selected && quote">
          <h3>{{ t('quote.' + selected.serviceCode) }}</h3>
          <p>
            {{ quote.shipment.originCountry }} {{ quote.shipment.originPostalCode }} →
            {{ quote.shipment.destinationCountry }} {{ quote.shipment.destinationPostalCode }}
          </p>
          <p>
            {{
              t('quote.parcelSummary', {
                grams: quote.shipment.weightGrams,
                length: quote.shipment.lengthMm,
                width: quote.shipment.widthMm,
                height: quote.shipment.heightMm,
              })
            }}
          </p>
          <dl class="quote-breakdown">
            <div
              v-for="key in [
                'baseMinor',
                'surchargeMinor',
                'marginMinor',
                'feeMinor',
                'netMinor',
                'taxMinor',
                'totalMinor',
              ] as const"
              :key="key"
            >
              <dt>{{ t('quote.' + key) }}</dt>
              <dd>{{ money(selected, selected.price[key]) }}</dd>
            </div>
          </dl>
          <p>{{ t('quote.syntheticTax') }}</p>
          <p>{{ t('quote.validUntil', { date: formatDate(selected.expiresAt) }) }}</p>
          <p v-if="expired" role="alert" class="feedback feedback-warning">
            {{ t('quote.expired') }}
          </p>
          <p>
            {{
              t('quote.provenance', {
                tariff: selected.tariffVersion,
                pricing: selected.price.pricingVersion,
                tax: selected.price.taxScenario,
              })
            }}
          </p>
          <p class="feedback feedback-info">{{ t('quote.noBooking') }}</p>
        </template>
      </AppModal>
    </main>
  </div>
</template>
