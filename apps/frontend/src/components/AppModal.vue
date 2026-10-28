<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from './AppIcon.vue'
const props = defineProps<{
  open: boolean
  title: string
  drawer?: boolean
  busy?: boolean
  side?: 'left' | 'right'
}>()
const emit = defineEmits<{ 'update:open': [open: boolean] }>()
const dialog = ref<HTMLDialogElement>(),
  titleId = useId(),
  { t } = useI18n()
function sync() {
  if (!dialog.value) return
  if (props.open && !dialog.value.open) dialog.value.showModal()
  else if (!props.open && dialog.value.open) dialog.value.close()
}
function trapFocus(event: KeyboardEvent) {
  if (event.key !== 'Tab' || !dialog.value) return
  const controls = Array.from(
    dialog.value.querySelectorAll<HTMLElement>(
      'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((element) => element.getClientRects().length > 0)
  const first = controls[0],
    last = controls[controls.length - 1]
  if (!first) {
    event.preventDefault()
    dialog.value.focus()
    return
  }
  if (
    event.shiftKey &&
    (document.activeElement === first || document.activeElement === dialog.value)
  ) {
    event.preventDefault()
    last.focus()
  } else if (
    !event.shiftKey &&
    (document.activeElement === last || document.activeElement === dialog.value)
  ) {
    event.preventDefault()
    first.focus()
  }
}
function close() {
  if (!props.busy) emit('update:open', false)
}
watch(() => props.open, sync, { flush: 'post' })
onMounted(sync)
onBeforeUnmount(() => dialog.value?.close())
</script>
<template>
  <Teleport to="body">
    <dialog
      ref="dialog"
      :class="['app-dialog', { 'app-drawer': drawer, 'drawer-left': side === 'left' }]"
      :aria-labelledby="titleId"
      tabindex="-1"
      @keydown="trapFocus"
      @cancel.prevent="close"
      @close="emit('update:open', false)"
    >
      <header class="dialog-header">
        <h2 :id="titleId">{{ title }}</h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          :disabled="busy"
          autofocus
          @click="close"
        >
          <AppIcon name="close" />
        </button>
      </header>
      <div class="dialog-content"><slot v-if="open" /></div>
      <footer v-if="$slots.footer" class="dialog-footer"><slot name="footer" /></footer>
    </dialog>
  </Teleport>
</template>
