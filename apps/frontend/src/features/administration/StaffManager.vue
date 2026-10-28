<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, type StaffPage, type Profile, type Role } from '../../api/client'
import { useSession } from '../../stores/session'
import FeedbackBox from '../../components/FeedbackBox.vue'
import PageControls from '../../components/PageControls.vue'
const session = useSession(),
  page = ref(1),
  search = ref(''),
  status = ref(''),
  role = ref('')
const result = ref<StaffPage>({ items: [], page: 1, pageSize: 20, total: 0 }),
  roles = ref<Role[]>([]),
  selected = ref<Profile | null>(null)
const error = ref(''),
  success = ref(''),
  loading = ref(false),
  busy = ref(false),
  showInvite = ref(false)
const email = ref(''),
  name = ref(''),
  inviteRoles = ref<string[]>([]),
  editRoles = ref<string[]>([])
async function load(next = 1) {
  loading.value = true
  error.value = ''
  page.value = next
  try {
    result.value = await api<StaffPage>(
      '/admin/staff?' +
        new URLSearchParams({
          page: String(next),
          search: search.value,
          status: status.value,
          role: role.value,
        }),
    )
  } catch (e) {
    error.value = String(e)
  } finally {
    loading.value = false
  }
}
async function detail(id: string) {
  error.value = ''
  try {
    selected.value = await api<Profile>('/admin/staff/' + id)
    editRoles.value = [...selected.value.roles]
  } catch (e) {
    error.value = String(e)
  }
}
async function invite() {
  busy.value = true
  error.value = ''
  try {
    await api('/admin/staff/invitations', 'POST', {
      email: email.value,
      name: name.value,
      roles: inviteRoles.value,
    })
    success.value = 'Invitation sent. The recipient can securely set their password.'
    showInvite.value = false
    email.value = ''
    name.value = ''
    inviteRoles.value = []
    await load()
  } catch (e) {
    error.value = String(e)
  } finally {
    busy.value = false
  }
}
async function update(kind: 'status' | 'roles') {
  if (
    !selected.value ||
    !window.confirm(
      kind === 'status'
        ? 'Change this staff account status? Existing sessions will be revoked.'
        : 'Replace this account’s roles? Existing sessions will be revoked.',
    )
  )
    return
  busy.value = true
  error.value = ''
  try {
    selected.value = await api<Profile>(
      '/admin/staff/' + selected.value.id + (kind === 'roles' ? '/roles' : ''),
      kind === 'roles' ? 'PUT' : 'PATCH',
      kind === 'roles'
        ? { roles: editRoles.value }
        : { status: selected.value.status === 'active' ? 'suspended' : 'active' },
    )
    success.value = 'Account updated. Existing sessions have been revoked.'
    await load(page.value)
  } catch (e) {
    error.value = String(e)
  } finally {
    busy.value = false
  }
}
onMounted(async () => {
  await load()
  if (session.can('roles.read')) {
    try {
      roles.value = (await api<Role[]>('/admin/roles')).filter((r) => r.code !== 'CUSTOMER')
    } catch (e) {
      error.value = String(e)
    }
  }
})
</script>
<template>
  <div>
    <div class="section-toolbar">
      <p class="muted">Manage the people behind your platform.</p>
      <button v-if="session.can('staff.invite')" @click="showInvite = !showInvite">
        + Invite team member
      </button>
    </div>
    <FeedbackBox :error="error" :success="success" :loading="loading" />
    <form v-if="showInvite" class="panel form-grid" @submit.prevent="invite">
      <h2 class="full">Invite a team member</h2>
      <label>Full name<input v-model="name" required minlength="2" maxlength="100" /></label
      ><label>Work email<input v-model="email" type="email" required /></label>
      <fieldset class="full">
        <legend>Staff roles</legend>
        <label v-for="r in roles" :key="r.code" class="check-label"
          ><input v-model="inviteRoles" type="checkbox" :value="r.code" />{{
            r.code.replaceAll('_', ' ')
          }}</label
        >
      </fieldset>
      <button :disabled="busy || !inviteRoles.length">Send invitation</button
      ><button type="button" class="secondary" @click="showInvite = false">Cancel</button>
    </form>
    <form class="filters" @submit.prevent="load()">
      <label>Search team<input v-model="search" placeholder="Name or email" /></label
      ><label
        >Status<select v-model="status">
          <option value="">All statuses</option>
          <option>active</option>
          <option>suspended</option>
          <option>invited</option>
        </select></label
      ><label
        >Role<select v-model="role">
          <option value="">All roles</option>
          <option v-for="r in roles" :key="r.code">{{ r.code }}</option>
        </select></label
      ><button class="secondary">Search</button>
    </form>
    <div class="panel table-panel">
      <table>
        <thead>
          <tr>
            <th>Team member</th>
            <th>Roles</th>
            <th>Status</th>
            <th><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="member in result.items" :key="member.id">
            <td>
              <strong>{{ member.name }}</strong
              ><small>{{ member.email }}</small>
            </td>
            <td>
              <span v-for="r in member.roles" :key="r" class="role-chip">{{
                r.replaceAll('_', ' ')
              }}</span>
            </td>
            <td>
              <span class="badge" :class="member.status">{{ member.status }}</span>
            </td>
            <td><button class="text-button" @click="detail(member.id)">View details →</button></td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && !result.items.length" class="empty">
        <h3>No team members found.</h3>
        <p>Try another search or invite your first team member.</p>
      </div>
      <PageControls
        :page="result.page"
        :page-size="result.pageSize"
        :total="result.total"
        @change="load"
      />
    </div>
    <section v-if="selected" class="panel detail-panel" aria-labelledby="staff-detail">
      <div class="section-toolbar">
        <h2 id="staff-detail">{{ selected.name }}</h2>
        <button class="secondary" @click="selected = null">Close details</button>
      </div>
      <p>{{ selected.email }} · {{ selected.status }}</p>
      <div class="form-grid">
        <fieldset v-if="session.can('roles.assign')">
          <legend>Assigned roles</legend>
          <label v-for="r in roles" :key="r.code" class="check-label"
            ><input v-model="editRoles" type="checkbox" :value="r.code" />{{
              r.code.replaceAll('_', ' ')
            }}</label
          ><button :disabled="busy || !editRoles.length" @click="update('roles')">
            Save roles
          </button>
        </fieldset>
        <div>
          <h3>Account access</h3>
          <p class="muted">Status and role changes invalidate active sessions.</p>
          <button
            v-if="session.can('staff.suspend') && ['active', 'suspended'].includes(selected.status)"
            class="secondary"
            :disabled="busy"
            @click="update('status')"
          >
            {{ selected.status === 'active' ? 'Suspend account' : 'Reactivate account' }}
          </button>
        </div>
      </div>
      <template v-if="selected.audit"
        ><h3>Recent account audit history</h3>
        <ul>
          <li v-for="event in selected.audit.items" :key="event.id">
            {{ event.action }} · {{ new Date(event.created_at).toLocaleString() }}
          </li>
        </ul></template
      >
    </section>
  </div>
</template>
