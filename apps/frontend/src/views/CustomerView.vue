<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useSession } from '../stores/session'
import { api, ApiError, type Profile } from '../api/client'
import { useFeedback } from '../composables/useFeedback'
import { validateFields } from '../i18n/validation'
import { translatedCode } from '../i18n'
import PublicHeader from '../components/PublicHeader.vue'
import PageHeader from '../components/PageHeader.vue'
import AppCard from '../components/AppCard.vue'
import AppButton from '../components/AppButton.vue'
import AppInput from '../components/AppInput.vue'
import AppBadge from '../components/AppBadge.vue'
import AppIcon from '../components/AppIcon.vue'
import FeedbackBox from '../components/FeedbackBox.vue'
import ShipIllustration from '../components/ShipIllustration.vue'
const session = useSession(),
  route = useRoute(),
  router = useRouter(),
  { t } = useI18n()
const name = ref(session.user?.name || ''),
  currentPassword = ref(''),
  password = ref(''),
  busy = ref(false),
  fields = ref<Record<string, string>>({}),
  { failure, success, errorText, successText, clear } = useFeedback()
const overview = computed(() => route.path === '/app'),
  security = computed(() => route.path === '/app/settings')
watch(
  () => route.path,
  () => {
    clear()
    fields.value = {}
    currentPassword.value = ''
    password.value = ''
  },
)
function fieldError(field: string) {
  return fields.value[field] ? t('errors.' + fields.value[field]) : undefined
}
async function save() {
  clear()
  fields.value = security.value
    ? validateFields(
        { currentPassword: currentPassword.value, password: password.value },
        { currentPassword: 'currentPassword', password: 'password' },
      )
    : validateFields({ name: name.value }, { name: 'name' })
  if (Object.keys(fields.value).length) {
    failure.value = new ApiError('', 400, 'request_invalid')
    return
  }
  busy.value = true
  try {
    if (security.value) {
      await api('/me/password', 'POST', {
        currentPassword: currentPassword.value,
        password: password.value,
      })
      session.user = null
      await router.push('/login')
    } else {
      session.user = await api<Profile>('/me', 'PATCH', { name: name.value })
      success.value = 'common.profileSaved'
    }
  } catch (e) {
    failure.value = e
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <div class="customer-page">
    <PublicHeader customer />
    <main id="main-content" class="customer-main content-width">
      <PageHeader
        :title="
          overview
            ? t('customer.welcome', { name: session.user?.name })
            : t(security ? 'navigation.settings' : 'navigation.profile')
        "
        :description="
          t(
            overview
              ? 'customer.description'
              : security
                ? 'customer.securityDescription'
                : 'customer.profileDescription',
          )
        "
        :icon="overview ? 'overview' : security ? 'lock' : 'user'"
      />
      <FeedbackBox :error="errorText" :success="successText" />
      <div v-if="overview" class="customer-grid">
        <AppCard :title="t('common.account')">
          <div class="profile-summary">
            <span class="user-avatar large">{{ session.user?.name.slice(0, 1) }}</span>
            <div>
              <h3>{{ session.user?.name }}</h3>
              <p>{{ session.user?.email }}</p>
            </div>
          </div>
          <AppBadge tone="success">
            {{ translatedCode('statuses', session.user?.status || '') }}
          </AppBadge>
          <p class="verified-note">
            <AppIcon name="check" :size="18" />
            {{ t('customer.verified') }}
          </p>
          <RouterLink class="text-link" to="/app/profile">
            {{ t('navigation.profile') }}
            <AppIcon name="right" :size="17" />
          </RouterLink>
        </AppCard>
        <AppCard class="future-shipping">
          <AppBadge>{{ t('quote.devSource') }}</AppBadge>
          <ShipIllustration />
          <h2>{{ t('customer.shippingTitle') }}</h2>
          <p>{{ t('customer.shippingBody') }}</p>
          <RouterLink class="app-button button-primary" to="/compare">
            {{ t('customer.compare') }}
          </RouterLink>
        </AppCard>
      </div>
      <AppCard
        v-else
        class="profile-card"
        :title="t(security ? 'common.account' : 'navigation.profile')"
      >
        <form novalidate @submit.prevent="save">
          <template v-if="!security">
            <AppInput
              :model-value="session.user?.email || ''"
              :label="t('common.email')"
              disabled
            />
            <AppInput
              v-model="name"
              :label="t('common.name')"
              :error="fieldError('name')"
              required
              :minlength="2"
              :maxlength="100"
              autocomplete="name"
            />
          </template>
          <template v-else>
            <AppInput
              v-model="currentPassword"
              :label="t('auth.currentPassword')"
              :error="fieldError('currentPassword')"
              type="password"
              required
              autocomplete="current-password"
            />
            <AppInput
              v-model="password"
              :label="t('auth.newPassword')"
              :hint="t('auth.passwordHint')"
              :error="fieldError('password')"
              type="password"
              required
              :minlength="12"
              :maxlength="128"
              autocomplete="new-password"
            />
            <p class="info-note">{{ t('customer.passwordNotice') }}</p>
          </template>
          <AppButton type="submit" :busy="busy">{{ t('common.save') }}</AppButton>
        </form>
      </AppCard>
    </main>
  </div>
</template>
