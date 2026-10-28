<script setup lang="ts">
import AppIcon from './AppIcon.vue'
</script>
<template>
  <RouterLink to="/" class="brand"
    ><span class="brand-icon"><AppIcon name="arrow" :size="24" /></span
    ><span>ShipWise<span class="brand-ai"> AI</span></span></RouterLink
  >
</template>
