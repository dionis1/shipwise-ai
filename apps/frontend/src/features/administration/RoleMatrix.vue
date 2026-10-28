<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, type Role } from '../../api/client'
import { useSession } from '../../stores/session'
import { useFeedback } from '../../composables/useFeedback'
import { translatedCode, formatNumber } from '../../i18n'
import AppCard from '../../components/AppCard.vue'
import AppTable from '../../components/AppTable.vue'
import AppButton from '../../components/AppButton.vue'
import AppModal from '../../components/AppModal.vue'
import AppSkeleton from '../../components/AppSkeleton.vue'
import FeedbackBox from '../../components/FeedbackBox.vue'
const session = useSession(),
  { t } = useI18n(),
  roles = ref<Role[]>([]),
  permissions = ref<string[]>([]),
  selected = ref<Role | null>(null),
  assigned = ref<string[]>([]),
  busy = ref(false),
  loading = ref(true),
  confirm = ref(false),
  { failure, success, errorText, successText } = useFeedback()
async function load() {
  try {
    roles.value = await api<Role[]>('/admin/roles')
    if (session.can('permissions.read'))
      permissions.value = await api<string[]>('/admin/permissions')
  } catch (e) {
    failure.value = e
  } finally {
    loading.value = false
  }
}
function select(role: Role) {
  selected.value = role
  assigned.value = [...role.permissions]
}
async function save() {
  if (!selected.value) return
  busy.value = true
  failure.value = undefined
  try {
    await api('/admin/roles/' + selected.value.code + '/permissions', 'PUT', {
      permissions: assigned.value,
    })
    success.value = 'access.saved'
    confirm.value = false
    selected.value = null
    await load()
  } catch (e) {
    failure.value = e
    confirm.value = false
  } finally {
    busy.value = false
  }
}
onMounted(load)
</script>
<template>
  <div>
    <FeedbackBox :error="errorText" :success="successText" />
    <AppSkeleton v-if="loading" />
    <AppCard v-else class="table-card">
      <AppTable :caption="t('navigation.access')" class="responsive-table">
        <thead>
          <tr>
            <th>{{ t('access.role') }}</th>
            <th>{{ t('access.responsibility') }}</th>
            <th>{{ t('common.permissions') }}</th>
            <th>
              <span class="sr-only">{{ t('common.actions') }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="role in roles" :key="role.code">
            <td>
              <strong>{{ translatedCode('roles', role.code) }}</strong>
              <small class="identifier">{{ role.code }}</small>
            </td>
            <td>{{ translatedCode('roleDescriptions', role.code) }}</td>
            <td :data-label="t('common.permissions')">
              {{ formatNumber(role.permissions.length) }}
            </td>
            <td>
              <AppButton variant="ghost" @click="select(role)">{{ t('access.view') }}</AppButton>
            </td>
          </tr>
        </tbody>
      </AppTable>
    </AppCard>
    <AppModal
      :open="!!selected"
      :title="selected ? translatedCode('roles', selected.code) : t('common.details')"
      drawer
      @update:open="
        (value) => {
          if (!value) selected = null
        }
      "
    >
      <template v-if="selected">
        <p>{{ translatedCode('roleDescriptions', selected.code) }}</p>
        <p class="info-note">{{ t('access.systemNotice') }}</p>
        <fieldset class="checkbox-list permission-list">
          <legend>{{ t('access.matrix') }}</legend>
          <label v-for="permission in permissions" :key="permission">
            <input
              v-model="assigned"
              type="checkbox"
              :value="permission"
              :disabled="
                !session.can('permissions.manage') ||
                ['SUPER_ADMIN', 'CUSTOMER'].includes(selected.code)
              "
            />
            <span>
              {{ translatedCode('permissionLabels', permission) }}
              <small class="identifier">{{ permission }}</small>
            </span>
          </label>
        </fieldset>
        <p v-if="!selected.permissions.length" class="muted">{{ t('access.empty') }}</p>
        <AppButton
          v-if="
            session.can('permissions.manage') &&
            !['SUPER_ADMIN', 'CUSTOMER'].includes(selected.code)
          "
          :disabled="busy"
          @click="confirm = true"
        >
          {{ t('access.save') }}
        </AppButton>
      </template>
    </AppModal>
    <AppModal v-model:open="confirm" :title="t('access.confirm')" :busy="busy">
      <p>{{ t('access.confirmBody') }}</p>
      <template #footer>
        <AppButton variant="secondary" :disabled="busy" @click="confirm = false">
          {{ t('common.cancel') }}
        </AppButton>
        <AppButton :busy="busy" @click="save">{{ t('common.continue') }}</AppButton>
      </template>
    </AppModal>
  </div>
</template>
