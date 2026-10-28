<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { setLocale, isLocale, type Locale } from '../i18n'
import { useSession } from '../stores/session'
import { errorMessage } from '../api/client'
import AppSelect from './AppSelect.vue'
const { t, locale } = useI18n(),
  session = useSession(),
  busy = ref(false),
  error = ref('')
const choice = computed({
  get: () => locale.value,
  set: (value) => {
    void change(value)
  },
})
async function change(value: string) {
  if (!isLocale(value)) return
  const previous = locale.value as Locale
  setLocale(value)
  error.value = ''
  busy.value = true
  try {
    if (session.user) await session.updateLocale(value)
  } catch (e) {
    setLocale(previous)
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <div class="language-control" data-testid="language-switcher">
    <AppSelect v-model="choice" :label="t('common.language')" compact :disabled="busy">
      <option value="en" lang="en">{{ t('languages.en') }}</option>
      <option value="de" lang="de">{{ t('languages.de') }}</option>
      <option value="fr" lang="fr">{{ t('languages.fr') }}</option>
    </AppSelect>
    <p v-if="error" role="alert" class="field-error">{{ error }}</p>
  </div>
</template>
