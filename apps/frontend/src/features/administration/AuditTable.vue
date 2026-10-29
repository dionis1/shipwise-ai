<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, type AuditPage, type AuditEvent } from '../../api/client'
import { useFeedback } from '../../composables/useFeedback'
import { translatedCode, formatDate, formatNumber } from '../../i18n'
import AppCard from '../../components/AppCard.vue'
import AppInput from '../../components/AppInput.vue'
import AppSelect from '../../components/AppSelect.vue'
import AppButton from '../../components/AppButton.vue'
import AppTable from '../../components/AppTable.vue'
import AppBadge from '../../components/AppBadge.vue'
import AppIcon from '../../components/AppIcon.vue'
import AppModal from '../../components/AppModal.vue'
import AppSkeleton from '../../components/AppSkeleton.vue'
import AppEmptyState from '../../components/AppEmptyState.vue'
import FeedbackBox from '../../components/FeedbackBox.vue'
import PageControls from '../../components/PageControls.vue'
const { t } = useI18n(),
  result = ref<AuditPage>({ items: [], total: 0, page: 1, pageSize: 20 }),
  action = ref(''),
  actor = ref(''),
  from = ref(''),
  to = ref(''),
  selected = ref<AuditEvent | null>(null),
  tab = ref('details'),
  loading = ref(false),
  { failure, errorText, clear } = useFeedback()
// Underscores within event codes are meaningful; maintain the explicit public audit vocabulary.
const eventCodes = [
  'carrier.configuration_changed',
  'carrier.sandbox_configuration_changed',
  'carrier.sandbox_checked',
  'tariff.imported',
  'pricing.rule_created',
  'auth.login',
  'auth.logout',
  'auth.login_failed',
  'account.registered',
  'account.verify',
  'account.reset',
  'account.invite',
  'account.reset_requested',
  'account.profile_updated',
  'account.password_changed',
  'staff.invited',
  'staff.status_changed',
  'staff.roles_changed',
  'role.permissions_changed',
  'staff.bootstrapped',
  'account.demo_created',
]
let generation = 0
watch([action, actor, from, to], () => {
  selected.value = null
})
function attention(event: AuditEvent) {
  return /failed|denied/.test(event.action) || event.context.status === 'suspended'
}
function select(event: AuditEvent) {
  selected.value = event
  tab.value = 'details'
}
async function load(page = 1) {
  const request = ++generation
  selected.value = null
  loading.value = true
  clear()
  try {
    const data = await api<AuditPage>(
      '/admin/audit-events?' +
        new URLSearchParams({
          page: String(page),
          action: action.value,
          actor_id: actor.value,
          from: from.value,
          to: to.value,
        }),
    )
    if (request === generation) result.value = data
  } catch (e) {
    if (request === generation) {
      failure.value = e
      result.value = { items: [], total: 0, page: 1, pageSize: 20 }
    }
  } finally {
    if (request === generation) loading.value = false
  }
}
function reset() {
  action.value = ''
  actor.value = ''
  from.value = ''
  to.value = ''
  void load()
}
onMounted(load)
</script>
<template>
  <div>
    <AppCard class="filter-card">
      <form class="audit-filters" @submit.prevent="load()">
        <AppSelect v-model="action" :label="t('audit.type')">
          <option value="">{{ t('audit.allTypes') }}</option>
          <option v-for="code in eventCodes" :key="code" :value="code">
            {{ translatedCode('events', code) }}
          </option>
        </AppSelect>
        <AppInput v-model="actor" :label="t('audit.actor')" :placeholder="t('audit.actorHint')" />
        <AppInput v-model="from" :label="t('audit.from')" type="date" />
        <AppInput v-model="to" :label="t('audit.to')" type="date" />
        <AppButton type="submit" :busy="loading" icon="search">{{ t('audit.search') }}</AppButton>
      </form>
      <button
        v-if="action || actor || from || to"
        type="button"
        class="text-button reset-filters"
        @click="reset"
      >
        {{ t('audit.clear') }}
      </button>
    </AppCard>
    <FeedbackBox :error="errorText" />
    <AppSkeleton v-if="loading" />
    <AppCard v-else class="table-card">
      <div class="results-header">
        <h2>{{ t('audit.found', { count: formatNumber(result.total) }, result.total) }}</h2>
        <AppBadge>{{ t('common.timezone') }}</AppBadge>
      </div>
      <AppTable v-if="result.items.length" :caption="t('navigation.audit')" class="audit-table">
        <thead>
          <tr>
            <th>{{ t('audit.time') }}</th>
            <th>{{ t('audit.event') }}</th>
            <th class="audit-actor">{{ t('audit.actor') }}</th>
            <th class="audit-target">{{ t('audit.target') }}</th>
            <th>{{ t('common.status') }}</th>
            <th>
              <span class="sr-only">{{ t('common.actions') }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="event in result.items"
            :key="event.id"
            :class="{ selected: selected?.id === event.id }"
          >
            <td class="event-time">{{ formatDate(event.created_at) }}</td>
            <td>
              <div class="event-label">
                <span :class="['event-icon', { 'event-warning': attention(event) }]">
                  <AppIcon
                    :name="
                      attention(event)
                        ? 'warning'
                        : event.action.includes('login')
                          ? 'login'
                          : event.action.includes('invite')
                            ? 'mail'
                            : 'audit'
                    "
                    :size="18"
                  />
                </span>
                <strong>{{ translatedCode('events', event.action) }}</strong>
              </div>
            </td>
            <td class="identifier audit-actor" :title="event.actor_id || undefined">
              {{ event.actor_id ? event.actor_id.slice(0, 12) + '…' : t('audit.system') }}
            </td>
            <td class="identifier audit-target" :title="event.target_id || undefined">
              {{ event.target_id ? event.target_id.slice(0, 12) + '…' : t('audit.noTarget') }}
            </td>
            <td>
              <AppBadge :tone="attention(event) ? 'warning' : 'success'">
                {{ t(attention(event) ? 'common.attention' : 'common.recorded') }}
              </AppBadge>
            </td>
            <td>
              <button
                class="icon-button"
                type="button"
                :aria-label="t('audit.open', { event: translatedCode('events', event.action) })"
                @click="select(event)"
              >
                <AppIcon name="right" :size="19" />
              </button>
            </td>
          </tr>
        </tbody>
      </AppTable>
      <AppEmptyState
        v-else
        :title="t('audit.empty')"
        :description="t('audit.emptyBody')"
        icon="audit"
      />
      <PageControls
        :page="result.page"
        :page-size="result.pageSize"
        :total="result.total"
        @change="load"
      />
    </AppCard>
    <AppModal
      :open="!!selected"
      :title="selected ? translatedCode('events', selected.action) : t('common.details')"
      drawer
      @update:open="
        (value) => {
          if (!value) selected = null
        }
      "
    >
      <template v-if="selected">
        <div class="event-detail-intro">
          <span :class="['feature-icon', { 'icon-warning': attention(selected) }]">
            <AppIcon :name="attention(selected) ? 'warning' : 'audit'" :size="26" />
          </span>
          <div>
            <AppBadge :tone="attention(selected) ? 'warning' : 'success'">
              {{ t(attention(selected) ? 'common.attention' : 'common.recorded') }}
            </AppBadge>
            <p>{{ formatDate(selected.created_at) }} · {{ t('common.timezone') }}</p>
          </div>
        </div>
        <div class="segmented-control" :aria-label="t('common.details')">
          <button
            type="button"
            :class="{ active: tab === 'details' }"
            :aria-pressed="tab === 'details'"
            @click="tab = 'details'"
          >
            {{ t('common.details') }}
          </button>
          <button
            type="button"
            :class="{ active: tab === 'raw' }"
            :aria-pressed="tab === 'raw'"
            @click="tab = 'raw'"
          >
            {{ t('audit.raw') }}
          </button>
        </div>
        <dl v-if="tab === 'details'" class="detail-list">
          <div>
            <dt>{{ t('audit.type') }}</dt>
            <dd>{{ translatedCode('events', selected.action) }}</dd>
          </div>
          <div>
            <dt>{{ t('audit.actor') }}</dt>
            <dd class="identifier">{{ selected.actor_id || t('audit.system') }}</dd>
          </div>
          <div>
            <dt>{{ t('audit.target') }}</dt>
            <dd class="identifier">{{ selected.target_id || t('audit.noTarget') }}</dd>
          </div>
          <div>
            <dt>{{ t('audit.time') }}</dt>
            <dd>{{ formatDate(selected.created_at) }}</dd>
          </div>
          <div>
            <dt>{{ t('audit.correlation') }}</dt>
            <dd class="identifier">{{ selected.correlation_id }}</dd>
          </div>
          <div v-if="selected.context.status">
            <dt>{{ t('common.status') }}</dt>
            <dd>{{ translatedCode('statuses', String(selected.context.status)) }}</dd>
          </div>
          <div v-if="selected.context.roles">
            <dt>{{ t('common.roles') }}</dt>
            <dd>
              {{
                String(selected.context.roles)
                  .split(',')
                  .map((role) => translatedCode('roles', role))
                  .join(', ')
              }}
            </dd>
          </div>
        </dl>
        <pre v-else class="raw-data">{{ JSON.stringify(selected, null, 2) }}</pre>
        <p v-if="!Object.keys(selected.context).length" class="muted">
          {{ t('audit.noMetadata') }}
        </p>
        <p class="info-note">
          <AppIcon name="lock" :size="17" />
          {{ t('audit.readOnly') }}
        </p>
      </template>
    </AppModal>
  </div>
</template>
