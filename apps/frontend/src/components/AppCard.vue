<script setup lang="ts">
defineProps<{ title?: string }>()
</script>
<template>
  <section class="app-card">
    <header v-if="title || $slots.header" class="card-header">
      <h2 v-if="title">{{ title }}</h2>
      <slot name="header" />
    </header>
    <slot />
  </section>
</template>
