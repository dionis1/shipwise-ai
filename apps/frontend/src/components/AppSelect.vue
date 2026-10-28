<script setup lang="ts">
import { useId } from 'vue'
const model = defineModel<string>({ default: '' })
defineProps<{ label: string; compact?: boolean; disabled?: boolean }>()
const id = useId()
</script>
<template>
  <div :class="['field', { 'compact-select': compact }]">
    <label :for="id" :class="{ 'sr-only': compact }">{{ label }}</label
    ><select :id="id" v-model="model" :disabled="disabled">
      <slot />
    </select>
  </div>
</template>
