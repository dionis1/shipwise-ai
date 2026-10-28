<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '../api/client'
import { useSession } from '../stores/session'
import { useFeedback } from '../composables/useFeedback'
import { validateFields } from '../i18n/validation'
import { landingFor } from '../router'
import BrandMark from '../components/BrandMark.vue'
import LanguageSwitcher from '../components/LanguageSwitcher.vue'
import FeedbackBox from '../components/FeedbackBox.vue'
import AppInput from '../components/AppInput.vue'
import AppButton from '../components/AppButton.vue'
import AppIcon from '../components/AppIcon.vue'
import ShipIllustration from '../components/ShipIllustration.vue'
const route = useRoute(),
  router = useRouter(),
  session = useSession(),
  { t, locale } = useI18n()
const email = ref(''),
  name = ref(''),
  password = ref(''),
  token = ref(new URLSearchParams(window.location.hash.slice(1)).get('token') || '')
if (window.location.hash)
  window.history.replaceState(
    window.history.state,
    '',
    window.location.pathname + window.location.search,
  )
const busy = ref(false),
  fieldErrors = ref<Record<string, string>>({}),
  { failure, success, errorText, successText, clear } = useFeedback()
const mode = computed(() => route.path.slice(1))
const names: Record<string, string> = {
  login: 'welcome',
  register: 'registerTitle',
  'verify-email': 'verifyTitle',
  'forgot-password': 'forgotTitle',
  'reset-password': 'resetTitle',
  'accept-invitation': 'inviteTitle',
}
const descriptions: Record<string, string> = {
  login: 'loginDescription',
  register: 'registerDescription',
  'verify-email': 'verifyDescription',
  'forgot-password': 'forgotDescription',
  'reset-password': 'resetDescription',
  'accept-invitation': 'inviteDescription',
}
const needsPassword = computed(() =>
  ['login', 'register', 'reset-password', 'accept-invitation'].includes(mode.value),
)
watch(mode, () => {
  clear()
  fieldErrors.value = {}
  password.value = ''
})
function fieldError(field: string) {
  return fieldErrors.value[field] ? t('errors.' + fieldErrors.value[field]) : undefined
}
async function submit() {
  clear()
  const rules: Parameters<typeof validateFields>[1] = {}
  if (['login', 'register', 'forgot-password'].includes(mode.value)) rules.email = 'email'
  if (mode.value === 'register') rules.name = 'name'
  if (needsPassword.value) rules.password = mode.value === 'login' ? 'currentPassword' : 'password'
  if (['verify-email', 'reset-password', 'accept-invitation'].includes(mode.value))
    rules.token = 'token'
  fieldErrors.value = validateFields(
    { email: email.value, name: name.value, password: password.value, token: token.value },
    rules,
  )
  if (Object.keys(fieldErrors.value).length) {
    failure.value = new ApiError('', 400, 'request_invalid')
    return
  }
  busy.value = true
  try {
    if (mode.value === 'login') {
      await session.login(email.value, password.value)
      const redirect = String(route.query.redirect || '')
      await router.push(
        redirect.startsWith('/') && !redirect.startsWith('//')
          ? redirect
          : landingFor(session.user!.permissions, session.user!.kind),
      )
    } else {
      const paths: Record<string, string> = {
        register: '/auth/register',
        'verify-email': '/auth/verify-email',
        'forgot-password': '/auth/password/forgot',
        'reset-password': '/auth/password/reset',
        'accept-invitation': '/auth/invitations/accept',
      }
      const data =
        mode.value === 'register'
          ? {
              email: email.value,
              name: name.value,
              password: password.value,
              preferredLocale: locale.value,
            }
          : mode.value === 'forgot-password'
            ? { email: email.value }
            : mode.value === 'verify-email'
              ? { token: token.value }
              : { token: token.value, password: password.value }
      await api(paths[mode.value], 'POST', data)
      success.value = (
        {
          register: 'auth.checkEmail',
          'forgot-password': 'auth.checkEmail',
          'verify-email': 'auth.verified',
          'reset-password': 'auth.resetDone',
          'accept-invitation': 'auth.accepted',
        } as Record<string, string>
      )[mode.value]
      password.value = ''
    }
  } catch (e) {
    failure.value = e
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <div class="auth-page">
    <aside class="auth-story">
      <BrandMark />
      <div class="auth-story-copy">
        <span class="eyebrow">{{ t('auth.secure') }}</span>
        <h2>{{ t('auth.storyTitle') }}</h2>
        <p>{{ t('auth.storyBody') }}</p>
        <ShipIllustration />
      </div>
      <p class="fine-print">{{ t('brand.foundation') }}</p>
    </aside>
    <section class="auth-content">
      <header class="auth-topbar">
        <RouterLink class="text-link" to="/">
          <AppIcon name="left" :size="17" />
          {{ t('auth.backHome') }}
        </RouterLink>
        <LanguageSwitcher />
      </header>
      <main id="main-content" class="auth-form">
        <span class="page-icon">
          <AppIcon
            :name="mode === 'login' ? 'login' : mode === 'register' ? 'user' : 'lock'"
            :size="27"
          />
        </span>
        <h1>{{ t('auth.' + names[mode]) }}</h1>
        <p class="page-description">{{ t('auth.' + descriptions[mode]) }}</p>
        <FeedbackBox :error="errorText" :success="successText" />
        <form novalidate @submit.prevent="submit">
          <AppInput
            v-if="mode === 'register'"
            v-model="name"
            :label="t('common.name')"
            :error="fieldError('name')"
            required
            :minlength="2"
            :maxlength="100"
            autocomplete="name"
          />
          <AppInput
            v-if="['login', 'register', 'forgot-password'].includes(mode)"
            v-model="email"
            :label="t('common.email')"
            :error="fieldError('email')"
            type="email"
            required
            autocomplete="email"
          />
          <AppInput
            v-if="needsPassword"
            v-model="password"
            :label="t(mode === 'login' ? 'auth.password' : 'auth.newPassword')"
            :hint="mode === 'login' ? undefined : t('auth.passwordHint')"
            :error="fieldError('password')"
            type="password"
            required
            :minlength="mode === 'login' ? 1 : 12"
            :maxlength="128"
            :autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
          />
          <AppInput
            v-if="['verify-email', 'reset-password', 'accept-invitation'].includes(mode)"
            v-model="token"
            :label="t('auth.token')"
            :hint="t('auth.tokenHint')"
            :error="fieldError('token')"
            required
            :minlength="64"
            :maxlength="64"
            autocomplete="off"
          />
          <AppButton type="submit" class="full-width" :busy="busy">
            {{ t(mode === 'login' ? 'auth.login' : 'common.continue') }}
            <AppIcon name="next" :size="18" />
          </AppButton>
        </form>
        <div class="auth-links">
          <RouterLink v-if="mode === 'login'" to="/forgot-password">
            {{ t('auth.forgot') }}
          </RouterLink>
          <RouterLink :to="mode === 'login' ? '/register' : '/login'">
            {{ t(mode === 'login' ? 'auth.register' : 'auth.backLogin') }}
          </RouterLink>
        </div>
        <p class="secure-note">
          <AppIcon name="audit" :size="16" />
          {{ t('auth.staffOnly') }}
        </p>
      </main>
    </section>
  </div>
</template>
