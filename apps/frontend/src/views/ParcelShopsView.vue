<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, errorMessage } from '../api/client'
import type { components } from '../api/generated'
import { formatDate, formatNumber } from '../i18n'
import PublicHeader from '../components/PublicHeader.vue'
import AppCard from '../components/AppCard.vue'
import AppInput from '../components/AppInput.vue'
import AppSelect from '../components/AppSelect.vue'
import AppButton from '../components/AppButton.vue'
import AppBadge from '../components/AppBadge.vue'
import AppEmptyState from '../components/AppEmptyState.vue'
import FeedbackBox from '../components/FeedbackBox.vue'
const { t } = useI18n()
const form = reactive({ country: 'DE', postalCode: '', city: '', carrier: 'all' })
const result = ref<components['schemas']['ParcelShops']>(),
  busy = ref(false),
  failure = ref<unknown>(),
  invalid = ref(false)
const error = computed(() =>
  invalid.value ? t('shops.searchInvalid') : failure.value ? errorMessage(failure.value) : '',
)
const available = computed(() => result.value?.outcomes.some((item) => item.status === 'available'))
watch(form, () => {
  result.value = undefined
  invalid.value = false
})
async function search() {
  invalid.value =
    !/^[A-Za-z0-9][A-Za-z0-9 -]{1,8}$/.test(form.postalCode.trim()) ||
    form.city.length > 50 ||
    /[<>]/.test(form.city) ||
    Array.from(form.city).some((character) => character.charCodeAt(0) < 32)
  failure.value = undefined
  result.value = undefined
  if (invalid.value) return
  busy.value = true
  try {
    const query = new URLSearchParams({
      country: form.country,
      postalCode: form.postalCode.trim(),
      carrier: form.carrier,
      limit: '10',
    })
    if (form.city.trim()) query.set('city', form.city.trim())
    result.value = await api<components['schemas']['ParcelShops']>('/parcel-shops?' + query)
  } catch (e) {
    failure.value = e
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <div>
    <PublicHeader />
    <main id="main-content" class="content-width compare-page">
      <div class="page-heading">
        <h1>{{ t('shops.title') }}</h1>
        <p>{{ t('shops.subtitle') }}</p>
      </div>
      <p class="feedback feedback-info">{{ t('shops.boundary') }}</p>
      <div class="quote-layout">
        <AppCard :title="t('shops.searchTitle')">
          <form novalidate @submit.prevent="search">
            <div class="quote-form-grid">
              <AppSelect v-model="form.country" :label="t('shops.country')" :disabled="busy">
                <option value="DE">{{ t('quote.DE') }}</option>
                <option value="FR">{{ t('quote.FR') }}</option>
              </AppSelect>
              <AppInput
                v-model="form.postalCode"
                :label="t('shops.postalCode')"
                :maxlength="9"
                :disabled="busy"
                :error="invalid ? t('shops.searchInvalid') : ''"
                required
                autocomplete="off"
              />
              <AppInput
                v-model="form.city"
                :label="t('shops.city')"
                :maxlength="50"
                :disabled="busy"
                autocomplete="off"
              />
              <AppSelect v-model="form.carrier" :label="t('shops.carrier')" :disabled="busy">
                <option value="all">{{ t('shops.allCarriers') }}</option>
                <option value="DPD">{{ t('sandbox.carriers.DPD') }}</option>
                <option value="GLS">{{ t('sandbox.carriers.GLS') }}</option>
              </AppSelect>
            </div>
            <p class="field-hint">{{ t('shops.privacy') }}</p>
            <FeedbackBox :error="error" :loading="busy" />
            <AppButton type="submit" :busy="busy">{{ t('shops.search') }}</AppButton>
          </form>
        </AppCard>
        <section :aria-label="t('shops.results')" :aria-busy="busy" aria-live="polite">
          <AppEmptyState
            v-if="!result && !busy && !failure"
            :title="t('shops.start')"
            :description="t('shops.startBody')"
          />
          <template v-if="result">
            <div
              v-for="outcome in result.outcomes"
              :key="outcome.carrier"
              class="feedback feedback-info"
              data-testid="shop-outcome"
            >
              <strong>{{ outcome.carrier }}</strong>
              · {{ t('sandbox.status.' + outcome.status) }}
            </div>
            <AppEmptyState
              v-if="!result.items.length"
              :title="t(available ? 'shops.empty' : 'shops.unavailable')"
              :description="t(available ? 'shops.emptyBody' : 'shops.unavailableBody')"
            />
            <ul v-else class="parcel-shop-list">
              <li v-for="shop in result.items" :key="shop.carrier + ':' + shop.id">
                <AppCard>
                  <div class="quote-offer-heading">
                    <h2>{{ shop.name || t('shops.unnamed') }}</h2>
                    <AppBadge>{{ shop.carrier }}</AppBadge>
                  </div>
                  <p v-if="shop.address || shop.postalCode || shop.city || shop.country">
                    {{
                      [shop.address, shop.postalCode, shop.city, shop.country]
                        .filter(Boolean)
                        .join(', ')
                    }}
                  </p>
                  <p>{{ t('shops.identifier') }}: {{ shop.id }}</p>
                  <p v-if="shop.latitude !== null && shop.longitude !== null">
                    {{ t('shops.coordinates') }}: {{ formatNumber(shop.latitude) }},
                    {{ formatNumber(shop.longitude) }}
                  </p>
                  <p v-if="shop.services.length">
                    {{ t('shops.services') }}: {{ shop.services.join(', ') }}
                  </p>
                  <p class="field-hint">
                    {{ t('shops.source') }}: {{ t('shops.' + shop.source) }} ·
                    {{ t('shops.retrieved') }}: {{ formatDate(shop.retrievedAt) }}
                  </p>
                </AppCard>
              </li>
            </ul>
          </template>
        </section>
      </div>
    </main>
  </div>
</template>
