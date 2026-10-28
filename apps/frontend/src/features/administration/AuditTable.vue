<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, type AuditPage, type AuditEvent } from '../../api/client'
import FeedbackBox from '../../components/FeedbackBox.vue'
import PageControls from '../../components/PageControls.vue'
const result = ref<AuditPage>({ items: [], total: 0, page: 1, pageSize: 20 }),
  action = ref(''),
  actor = ref(''),
  from = ref(''),
  to = ref(''),
  selected = ref<AuditEvent | null>(null),
  error = ref(''),
  loading = ref(false)
async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    result.value = await api<AuditPage>(
      '/admin/audit-events?' +
        new URLSearchParams({
          page: String(page),
          action: action.value,
          actor_id: actor.value,
          from: from.value,
          to: to.value ? to.value + 'T23:59:59Z' : '',
        }),
    )
  } catch (e) {
    error.value = String(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>
<template>
  <div>
    <p class="muted">A clear record of access and sensitive account changes.</p>
    <FeedbackBox :error="error" :loading="loading" />
    <form class="filters" @submit.prevent="load()">
      <label>Event type<input v-model="action" placeholder="e.g. staff.invited" /></label
      ><label>Actor ID<input v-model="actor" placeholder="Exact account ID" /></label
      ><label>From date (UTC)<input v-model="from" type="date" /></label
      ><label>To date (UTC)<input v-model="to" type="date" /></label
      ><button class="secondary">Filter events</button>
    </form>
    <div class="panel table-panel">
      <table>
        <thead>
          <tr>
            <th>Event</th>
            <th>Actor</th>
            <th>Timestamp</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="event in result.items" :key="event.id">
            <td>
              <strong>{{ event.action }}</strong
              ><small>{{ event.target_id || 'No target account' }}</small>
            </td>
            <td class="mono">{{ event.actor_id || 'System / anonymous' }}</td>
            <td>{{ new Date(event.created_at).toLocaleString() }}</td>
            <td><button class="text-button" @click="selected = event">Details →</button></td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && !result.items.length" class="empty">
        <h3>No matching events.</h3>
        <p>Security events will appear here as accounts are used.</p>
      </div>
      <PageControls
        :page="result.page"
        :page-size="result.pageSize"
        :total="result.total"
        @change="load"
      />
    </div>
    <section v-if="selected" class="panel detail-panel">
      <div class="section-toolbar">
        <h2>{{ selected.action }}</h2>
        <button class="secondary" @click="selected = null">Close event</button>
      </div>
      <p class="mono">Correlation: {{ selected.correlation_id }}</p>
      <pre>{{ JSON.stringify(selected.context, null, 2) }}</pre>
      <p class="fine">Audit records are read-only. Sensitive context is excluded at collection.</p>
    </section>
  </div>
</template>
