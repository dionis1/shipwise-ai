<script setup lang="ts">
withDefaults(defineProps<{ tone?: string }>(), { tone: 'neutral' })
</script>
<template>
  <span :class="['app-badge', 'badge-' + tone]"><slot /></span>
</template>
