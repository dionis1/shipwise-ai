<script setup lang="ts">
defineProps<{ caption: string }>()
</script>
<template>
  <div class="table-wrap">
    <table class="app-table">
      <caption class="sr-only">
        {{
          caption
        }}
      </caption>
      <slot />
    </table>
  </div>
</template>
