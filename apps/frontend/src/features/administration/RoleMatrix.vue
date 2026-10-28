<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, type Role } from '../../api/client'
import { useSession } from '../../stores/session'
import FeedbackBox from '../../components/FeedbackBox.vue'
const session = useSession(),
  roles = ref<Role[]>([]),
  permissions = ref<string[]>([]),
  selected = ref<Role | null>(null),
  assigned = ref<string[]>([]),
  error = ref(''),
  success = ref(''),
  busy = ref(false)
async function load() {
  try {
    roles.value = await api<Role[]>('/admin/roles')
    if (session.can('permissions.read'))
      permissions.value = await api<string[]>('/admin/permissions')
  } catch (e) {
    error.value = String(e)
  }
}
function select(role: Role) {
  selected.value = role
  assigned.value = [...role.permissions]
}
async function save() {
  if (
    !selected.value ||
    !window.confirm('Update this role’s permissions? All affected sessions will be revoked.')
  )
    return
  busy.value = true
  error.value = ''
  try {
    await api('/admin/roles/' + selected.value.code + '/permissions', 'PUT', {
      permissions: assigned.value,
    })
    success.value = 'Permissions updated. Affected sessions were revoked.'
    await load()
    selected.value = null
  } catch (e) {
    error.value = String(e)
  } finally {
    busy.value = false
  }
}
onMounted(load)
</script>
<template>
  <div>
    <p class="muted">
      Explicit permissions. Clear responsibilities. Every check enforced by the API.
    </p>
    <FeedbackBox :error="error" :success="success" />
    <div class="panel table-panel">
      <table>
        <thead>
          <tr>
            <th>Role</th>
            <th>Responsibility</th>
            <th>Permissions</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="role in roles" :key="role.code">
            <td>
              <strong>{{ role.code.replaceAll('_', ' ') }}</strong>
            </td>
            <td>{{ role.description }}</td>
            <td>{{ role.permissions.length }}</td>
            <td><button class="text-button" @click="select(role)">View role →</button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <section v-if="selected" class="panel detail-panel">
      <div class="section-toolbar">
        <h2>{{ selected.code.replaceAll('_', ' ') }}</h2>
        <button class="secondary" @click="selected = null">Close role</button>
      </div>
      <p>{{ selected.description }}</p>
      <p class="fine">
        System roles are protected. Only Super Administrators can update other role permissions.
      </p>
      <fieldset>
        <legend>Permission matrix for this role</legend>
        <label v-for="permission in permissions" :key="permission" class="check-label"
          ><input
            v-model="assigned"
            type="checkbox"
            :value="permission"
            :disabled="
              !session.can('permissions.manage') ||
              ['SUPER_ADMIN', 'CUSTOMER'].includes(selected.code)
            "
          />{{ permission }}</label
        >
      </fieldset>
      <p v-if="!selected.permissions.length" class="muted">
        No staff permissions. Customers can access their own account only.
      </p>
      <button
        v-if="
          session.can('permissions.manage') && !['SUPER_ADMIN', 'CUSTOMER'].includes(selected.code)
        "
        :disabled="busy"
        @click="save"
      >
        Save permissions
      </button>
    </section>
  </div>
</template>
