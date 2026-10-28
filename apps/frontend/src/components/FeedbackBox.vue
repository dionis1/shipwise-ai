<script setup lang="ts">
defineProps<{ error?: string; success?: string; loading?: boolean }>()
</script>
<template>
  <p v-if="loading" role="status" class="feedback">Loading your workspace…</p>
  <p v-if="error" role="alert" class="feedback error">{{ error }}</p>
  <p v-if="success" role="status" class="feedback success">{{ success }}</p>
</template>
