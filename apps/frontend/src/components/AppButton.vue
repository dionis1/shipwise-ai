<script setup lang="ts">
import AppIcon from './AppIcon.vue'
withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
    type?: 'button' | 'submit'
    busy?: boolean
    disabled?: boolean
    icon?: string
  }>(),
  { variant: 'primary', type: 'button', icon: undefined },
)
</script>
<template>
  <button
    :type="type"
    :class="['app-button', 'button-' + variant]"
    :disabled="disabled || busy"
    :aria-busy="busy"
  >
    <AppIcon
      v-if="busy || icon"
      :name="busy ? 'loading' : icon!"
      :class="{ spin: busy }"
      :size="18"
    />
    <slot />
  </button>
</template>
