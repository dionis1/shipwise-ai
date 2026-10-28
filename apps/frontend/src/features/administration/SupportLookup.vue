<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, type CustomerPage } from '../../api/client'
import { translatedCode } from '../../i18n'
import { useFeedback } from '../../composables/useFeedback'
import AppCard from '../../components/AppCard.vue'
import AppInput from '../../components/AppInput.vue'
import AppButton from '../../components/AppButton.vue'
import AppTable from '../../components/AppTable.vue'
import AppBadge from '../../components/AppBadge.vue'
import AppEmptyState from '../../components/AppEmptyState.vue'
import FeedbackBox from '../../components/FeedbackBox.vue'
import PageControls from '../../components/PageControls.vue'
const { t } = useI18n(),
  search = ref(''),
  result = ref<CustomerPage | null>(null),
  busy = ref(false),
  { failure, errorText, clear } = useFeedback()
async function load(page = 1) {
  clear()
  busy.value = true
  try {
    result.value = await api<CustomerPage>(
      '/admin/customers?' + new URLSearchParams({ search: search.value, page: String(page) }),
    )
  } catch (e) {
    failure.value = e
    result.value = null
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <AppCard :title="t('support.title')">
    <p class="muted">{{ t('support.description') }}</p>
    <form class="support-search" @submit.prevent="load()">
      <AppInput v-model="search" :label="t('support.search')" type="search" />
      <AppButton type="submit" icon="search" :busy="busy">{{ t('support.button') }}</AppButton>
    </form>
    <FeedbackBox :error="errorText" :loading="busy" />
    <template v-if="result && !busy">
      <AppTable v-if="result.items.length" :caption="t('support.title')" class="responsive-table">
        <thead>
          <tr>
            <th>{{ t('common.name') }}</th>
            <th>{{ t('common.email') }}</th>
            <th>{{ t('common.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="customer in result.items" :key="customer.id">
            <td>
              <strong>{{ customer.name }}</strong>
            </td>
            <td>{{ customer.email }}</td>
            <td>
              <AppBadge :tone="customer.status === 'active' ? 'success' : 'neutral'">
                {{ translatedCode('statuses', customer.status) }}
              </AppBadge>
            </td>
          </tr>
        </tbody>
      </AppTable>
      <AppEmptyState v-else :title="t('support.empty')" icon="search" />
      <PageControls
        :page="result.page"
        :page-size="result.pageSize"
        :total="result.total"
        @change="load"
      />
    </template>
  </AppCard>
</template>
