<script setup lang="ts">
import { ref } from 'vue'
import { api, type CustomerPage } from '../../api/client'
import FeedbackBox from '../../components/FeedbackBox.vue'
import PageControls from '../../components/PageControls.vue'
const search = ref(''),
  result = ref<CustomerPage | null>(null),
  error = ref(''),
  busy = ref(false)
async function load(page = 1) {
  busy.value = true
  error.value = ''
  try {
    result.value = await api<CustomerPage>(
      '/admin/customers?' + new URLSearchParams({ search: search.value, page: String(page) }),
    )
  } catch (e) {
    error.value = String(e)
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <section class="panel">
    <h2>Customer lookup</h2>
    <p class="muted">Only account name, email and status are available to support.</p>
    <form class="filters" @submit.prevent="load()">
      <label
        >Email search<input v-model="search" type="search" placeholder="Customer email" /></label
      ><button :disabled="busy">Search accounts</button>
    </form>
    <FeedbackBox :error="error" :loading="busy" /><template v-if="result"
      ><table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="customer in result.items" :key="customer.id">
            <td>{{ customer.name }}</td>
            <td>{{ customer.email }}</td>
            <td>{{ customer.status }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="!result.items.length">No matching customer accounts.</p>
      <PageControls
        :page="result.page"
        :page-size="result.pageSize"
        :total="result.total"
        @change="load"
    /></template>
  </section>
</template>
