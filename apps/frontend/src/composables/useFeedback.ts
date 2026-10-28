import { computed, ref, shallowRef } from 'vue'
import { errorMessage } from '../api/client'
import { i18n } from '../i18n'
export function useFeedback() {
  const failure = shallowRef<unknown>(),
    success = ref('')
  const errorText = computed(() => (failure.value ? errorMessage(failure.value) : undefined))
  const successText = computed(() => (success.value ? i18n.global.t(success.value) : undefined))
  function clear() {
    failure.value = undefined
    success.value = ''
  }
  return { failure, success, errorText, successText, clear }
}
