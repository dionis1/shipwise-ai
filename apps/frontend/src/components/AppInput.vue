<script setup lang="ts">
import { useId } from 'vue'
const model = defineModel<string>({ default: '' })
withDefaults(
  defineProps<{
    label: string
    type?: string
    placeholder?: string
    hint?: string
    error?: string
    required?: boolean
    disabled?: boolean
    autocomplete?: string
    minlength?: number
    maxlength?: number
  }>(),
  {
    type: 'text',
    placeholder: undefined,
    hint: undefined,
    error: undefined,
    autocomplete: undefined,
    minlength: undefined,
    maxlength: undefined,
  },
)
const id = useId()
</script>
<template>
  <div class="field">
    <label :for="id">
      {{ label }}
      <span v-if="required" class="required-marker" aria-hidden="true">*</span>
    </label>
    <input
      :id="id"
      v-model="model"
      :type="type"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :autocomplete="autocomplete"
      :minlength="minlength"
      :maxlength="maxlength"
      :aria-invalid="error ? true : undefined"
      :aria-describedby="error || hint ? id + '-hint' : undefined"
    />
    <p v-if="error || hint" :id="id + '-hint'" :class="error ? 'field-error' : 'field-hint'">
      {{ error || hint }}
    </p>
  </div>
</template>
