<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, ApiError, type StaffPage, type Profile, type Role } from '../../api/client'
import { useSession } from '../../stores/session'
import { useFeedback } from '../../composables/useFeedback'
import { validateFields } from '../../i18n/validation'
import { translatedCode, formatDate } from '../../i18n'
import AppButton from '../../components/AppButton.vue'
import AppInput from '../../components/AppInput.vue'
import AppSelect from '../../components/AppSelect.vue'
import AppCard from '../../components/AppCard.vue'
import AppTable from '../../components/AppTable.vue'
import AppBadge from '../../components/AppBadge.vue'
import AppModal from '../../components/AppModal.vue'
import AppSkeleton from '../../components/AppSkeleton.vue'
import AppEmptyState from '../../components/AppEmptyState.vue'
import FeedbackBox from '../../components/FeedbackBox.vue'
import PageControls from '../../components/PageControls.vue'
const { t, locale } = useI18n(),
  session = useSession(),
  search = ref(''),
  status = ref(''),
  role = ref('')
const result = ref<StaffPage>({ items: [], total: 0, page: 1, pageSize: 20 }),
  roles = ref<Role[]>([]),
  selected = ref<Profile | null>(null),
  editRoles = ref<string[]>([])
const loading = ref(false),
  busy = ref(false),
  showInvite = ref(false),
  confirmation = ref<'roles' | 'status' | null>(null),
  fields = ref<Record<string, string>>({}),
  { failure, success, errorText, successText, clear } = useFeedback()
const email = ref(''),
  name = ref(''),
  inviteRoles = ref<string[]>([]),
  inviteLocale = ref(locale.value)
let generation = 0
function fieldError(field: string) {
  return fields.value[field] ? t('errors.' + fields.value[field]) : undefined
}
async function load(page = 1) {
  const request = ++generation
  loading.value = true
  try {
    const data = await api<StaffPage>(
      '/admin/staff?' +
        new URLSearchParams({
          page: String(page),
          search: search.value,
          status: status.value,
          role: role.value,
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
async function detail(id: string) {
  clear()
  try {
    selected.value = await api<Profile>('/admin/staff/' + id)
    editRoles.value = [...selected.value.roles]
  } catch (e) {
    failure.value = e
  }
}
async function invite() {
  clear()
  fields.value = validateFields(
    { email: email.value, name: name.value },
    { email: 'email', name: 'name' },
  )
  if (Object.keys(fields.value).length || !inviteRoles.value.length) {
    failure.value = new ApiError(
      '',
      400,
      !inviteRoles.value.length ? 'roles_required' : 'request_invalid',
    )
    return
  }
  busy.value = true
  try {
    await api('/admin/staff/invitations', 'POST', {
      email: email.value,
      name: name.value,
      roles: inviteRoles.value,
      preferredLocale: inviteLocale.value,
    })
    success.value = 'staff.invited'
    showInvite.value = false
    email.value = ''
    name.value = ''
    inviteRoles.value = []
    await load()
  } catch (e) {
    failure.value = e
  } finally {
    busy.value = false
  }
}
async function update() {
  if (!selected.value || !confirmation.value) return
  const kind = confirmation.value
  clear()
  busy.value = true
  try {
    selected.value = await api<Profile>(
      '/admin/staff/' + selected.value.id + (kind === 'roles' ? '/roles' : ''),
      kind === 'roles' ? 'PUT' : 'PATCH',
      kind === 'roles'
        ? { roles: editRoles.value }
        : { status: selected.value.status === 'active' ? 'suspended' : 'active' },
    )
    success.value = 'staff.updated'
    confirmation.value = null
    await load(result.value.page)
  } catch (e) {
    failure.value = e
    confirmation.value = null
  } finally {
    busy.value = false
  }
}
function openInvite() {
  showInvite.value = true
  clear()
}
function searchStaff() {
  clear()
  selected.value = null
  void load()
}
onMounted(async () => {
  await load()
  if (session.can('roles.read')) {
    try {
      roles.value = (await api<Role[]>('/admin/roles')).filter((role) => role.code !== 'CUSTOMER')
    } catch (e) {
      failure.value = e
    }
  }
})
</script>
<template>
  <div>
    <div class="section-toolbar">
      <span></span>
      <AppButton v-if="session.can('staff.invite')" icon="plus" @click="openInvite">
        {{ t('staff.invite') }}
      </AppButton>
    </div>
    <FeedbackBox :error="errorText" :success="successText" />
    <AppCard class="filter-card">
      <form class="staff-filters" @submit.prevent="searchStaff">
        <AppInput
          v-model="search"
          :label="t('staff.search')"
          :placeholder="t('staff.searchHint')"
        />
        <AppSelect v-model="status" :label="t('common.status')">
          <option value="">{{ t('common.allStatuses') }}</option>
          <option v-for="value in ['active', 'suspended', 'invited']" :key="value" :value="value">
            {{ translatedCode('statuses', value) }}
          </option>
        </AppSelect>
        <AppSelect v-model="role" :label="t('access.role')">
          <option value="">{{ t('common.allRoles') }}</option>
          <option v-for="item in roles" :key="item.code" :value="item.code">
            {{ translatedCode('roles', item.code) }}
          </option>
        </AppSelect>
        <AppButton variant="secondary" type="submit" :busy="loading" icon="search">
          {{ t('common.search') }}
        </AppButton>
      </form>
    </AppCard>
    <AppSkeleton v-if="loading" />
    <AppCard v-else class="table-card">
      <AppTable
        v-if="result.items.length"
        :caption="t('navigation.staff')"
        class="responsive-table"
      >
        <thead>
          <tr>
            <th>{{ t('staff.member') }}</th>
            <th>{{ t('common.roles') }}</th>
            <th>{{ t('common.status') }}</th>
            <th>
              <span class="sr-only">{{ t('common.actions') }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="member in result.items" :key="member.id">
            <td>
              <div class="member-cell">
                <span class="user-avatar">{{ member.name.slice(0, 1) }}</span>
                <div>
                  <strong>{{ member.name }}</strong>
                  <small>{{ member.email }}</small>
                </div>
              </div>
            </td>
            <td :data-label="t('common.roles')">
              <span v-for="code in member.roles" :key="code" class="role-chip">
                {{ translatedCode('roles', code) }}
              </span>
            </td>
            <td :data-label="t('common.status')">
              <AppBadge
                :tone="
                  member.status === 'active'
                    ? 'success'
                    : member.status === 'suspended'
                      ? 'warning'
                      : 'info'
                "
              >
                {{ translatedCode('statuses', member.status) }}
              </AppBadge>
            </td>
            <td>
              <AppButton
                variant="ghost"
                :aria-label="t('staff.viewAccount', { name: member.name })"
                @click="detail(member.id)"
              >
                {{ t('staff.details') }}
              </AppButton>
            </td>
          </tr>
        </tbody>
      </AppTable>
      <AppEmptyState
        v-else
        :title="t('staff.empty')"
        :description="t('staff.emptyBody')"
        icon="staff"
      />
      <PageControls
        :page="result.page"
        :page-size="result.pageSize"
        :total="result.total"
        @change="load"
      />
    </AppCard>
    <AppModal v-model:open="showInvite" :title="t('staff.inviteTitle')" :busy="busy">
      <FeedbackBox :error="errorText" />
      <form novalidate @submit.prevent="invite">
        <AppInput
          v-model="name"
          :label="t('common.name')"
          :error="fieldError('name')"
          required
          :minlength="2"
          :maxlength="100"
          autocomplete="name"
        />
        <AppInput
          v-model="email"
          :label="t('staff.workEmail')"
          :error="fieldError('email')"
          type="email"
          required
          autocomplete="email"
        />
        <AppSelect v-model="inviteLocale" :label="t('staff.inviteLanguage')">
          <option v-for="value in ['en', 'de', 'fr']" :key="value" :value="value">
            {{ t('languages.' + value) }}
          </option>
        </AppSelect>
        <fieldset class="checkbox-list">
          <legend>{{ t('common.roles') }}</legend>
          <label v-for="item in roles" :key="item.code">
            <input v-model="inviteRoles" type="checkbox" :value="item.code" />
            {{ translatedCode('roles', item.code) }}
          </label>
        </fieldset>
        <div class="dialog-actions">
          <AppButton variant="secondary" :disabled="busy" @click="showInvite = false">
            {{ t('common.cancel') }}
          </AppButton>
          <AppButton type="submit" :busy="busy">{{ t('staff.send') }}</AppButton>
        </div>
      </form>
    </AppModal>
    <AppModal
      :open="!!selected"
      :title="selected?.name || t('common.details')"
      drawer
      @update:open="
        (value) => {
          if (!value) selected = null
        }
      "
    >
      <template v-if="selected">
        <FeedbackBox :error="errorText" :success="successText" />
        <p class="muted">{{ selected.email }}</p>
        <AppBadge :tone="selected.status === 'active' ? 'success' : 'warning'">
          {{ translatedCode('statuses', selected.status) }}
        </AppBadge>
        <section class="drawer-section">
          <h3>{{ t('staff.accountAccess') }}</h3>
          <p>{{ t('staff.revokeNotice') }}</p>
          <AppButton
            v-if="session.can('staff.suspend') && ['active', 'suspended'].includes(selected.status)"
            :variant="selected.status === 'active' ? 'danger' : 'secondary'"
            :disabled="busy"
            @click="confirmation = 'status'"
          >
            {{ t(selected.status === 'active' ? 'staff.suspend' : 'staff.reactivate') }}
          </AppButton>
        </section>
        <section v-if="session.can('roles.assign')" class="drawer-section">
          <fieldset class="checkbox-list">
            <legend>{{ t('staff.assignedRoles') }}</legend>
            <label v-for="item in roles" :key="item.code">
              <input v-model="editRoles" type="checkbox" :value="item.code" />
              {{ translatedCode('roles', item.code) }}
            </label>
          </fieldset>
          <AppButton :disabled="busy || !editRoles.length" @click="confirmation = 'roles'">
            {{ t('staff.saveRoles') }}
          </AppButton>
        </section>
        <section v-if="selected.audit" class="drawer-section">
          <h3>{{ t('staff.history') }}</h3>
          <ul class="compact-activity-list">
            <li v-for="event in selected.audit.items" :key="event.id">
              <strong>{{ translatedCode('events', event.action) }}</strong>
              <small>{{ formatDate(event.created_at) }}</small>
            </li>
          </ul>
        </section>
      </template>
    </AppModal>
    <AppModal
      :open="!!confirmation"
      :title="t(confirmation === 'roles' ? 'staff.confirmRoles' : 'staff.confirmStatus')"
      :busy="busy"
      @update:open="
        (value) => {
          if (!value) confirmation = null
        }
      "
    >
      <p>{{ t('staff.confirmBody') }}</p>
      <template #footer>
        <AppButton variant="secondary" :disabled="busy" @click="confirmation = null">
          {{ t('common.cancel') }}
        </AppButton>
        <AppButton :busy="busy" @click="update">{{ t('common.continue') }}</AppButton>
      </template>
    </AppModal>
  </div>
</template>
