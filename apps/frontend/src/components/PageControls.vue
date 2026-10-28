<script setup lang="ts">
defineProps<{ page: number; total: number; pageSize: number }>()
defineEmits<{ change: [page: number] }>()
</script>
<template><div class="pagination"><span>{{ total }} results · Page {{ page }}</span><div><button class="secondary" :disabled="page <= 1" @click="$emit('change', page - 1)">Previous</button> <button class="secondary" :disabled="page * pageSize >= total" @click="$emit('change', page + 1)">Next</button></div></div></template>