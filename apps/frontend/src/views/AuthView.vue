<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client'
import { useSession } from '../stores/session'
import { landingFor } from '../router'
import BrandMark from '../components/BrandMark.vue'
import FeedbackBox from '../components/FeedbackBox.vue'
const route = useRoute(),
  router = useRouter(),
  session = useSession()
const email = ref(''),
  name = ref(''),
  password = ref(''),
  token = ref(new URLSearchParams(window.location.hash.slice(1)).get('token') || '')
if (window.location.hash)
  window.history.replaceState(null, '', window.location.pathname + window.location.search)
const busy = ref(false),
  error = ref(''),
  success = ref('')
const mode = computed(() => route.path.slice(1))
const titles: Record<string, string> = {
  login: 'Welcome back.',
  register: 'Your journey starts here.',
  'verify-email': 'Verify your email.',
  'forgot-password': 'Let’s get you back in.',
  'reset-password': 'Choose a new password.',
  'accept-invitation': 'Welcome to the team.',
}
const needsPassword = computed(() =>
  ['login', 'register', 'reset-password', 'accept-invitation'].includes(mode.value),
)
async function submit() {
  busy.value = true
  error.value = ''
  success.value = ''
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
          ? { email: email.value, name: name.value, password: password.value }
          : mode.value === 'forgot-password'
            ? { email: email.value }
            : mode.value === 'verify-email'
              ? { token: token.value }
              : { token: token.value, password: password.value }
      const result = await api<{ message: string }>(paths[mode.value], 'POST', data)
      success.value = result.message
      password.value = ''
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Unable to complete this request.'
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <main class="auth-page">
    <aside class="auth-story">
      <BrandMark />
      <div>
        <span class="eyebrow">THE JOURNEY AHEAD</span>
        <h1>One account.<br />New possibilities.</h1>
        <p>A considered approach to shipping, starting with you.</p>
      </div>
      <span class="fine">Secure access · ShipWise AI</span>
    </aside>
    <section class="auth-form">
      <RouterLink class="back-link" to="/">← Back to ShipWise</RouterLink
      ><span class="eyebrow">YOUR SHIPWISE ACCOUNT</span>
      <h2>{{ titles[mode] }}</h2>
      <p class="muted">
        {{
          mode === 'login'
            ? 'Sign in to continue to your workspace.'
            : 'A secure foundation for what comes next.'
        }}
      </p>
      <FeedbackBox :error="error" :success="success" />
      <form @submit.prevent="submit">
        <label v-if="mode === 'register'"
          >Full name<input
            v-model="name"
            required
            minlength="2"
            maxlength="100"
            autocomplete="name" /></label
        ><label v-if="['login', 'register', 'forgot-password'].includes(mode)"
          >Email address<input v-model="email" type="email" required autocomplete="email" /></label
        ><label v-if="needsPassword"
          >{{ mode === 'login' ? 'Password' : 'New password · at least 12 characters'
          }}<input
            v-model="password"
            type="password"
            required
            :minlength="mode === 'login' ? 1 : 12"
            maxlength="128"
            :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" /></label
        ><label v-if="['verify-email', 'reset-password', 'accept-invitation'].includes(mode)"
          >Secure token<input
            v-model="token"
            required
            minlength="64"
            maxlength="64"
            autocomplete="off" /></label
        ><button :disabled="busy" class="wide">
          {{ busy ? 'Please wait…' : mode === 'login' ? 'Sign in →' : 'Continue →' }}
        </button>
      </form>
      <div class="auth-links">
        <RouterLink v-if="mode === 'login'" to="/forgot-password">Forgot password?</RouterLink
        ><RouterLink :to="mode === 'login' ? '/register' : '/login'">{{
          mode === 'login' ? 'Create an account' : 'Back to sign in'
        }}</RouterLink>
      </div>
      <p class="fine">Staff access is by invitation only.</p>
    </section>
  </main>
</template>
