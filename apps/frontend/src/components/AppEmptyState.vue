<script setup lang="ts">
import AppIcon from './AppIcon.vue'
withDefaults(defineProps<{ title: string; description?: string; icon?: string }>(), {
  icon: 'empty',
  description: undefined,
})
</script>
<template>
  <div class="empty-state">
    <span class="feature-icon"><AppIcon :name="icon" :size="26" /></span>
    <h3>{{ title }}</h3>
    <p v-if="description">{{ description }}</p>
    <slot />
  </div>
</template>
