<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminLinks } from '../app/navigation'
import { useSession } from '../stores/session'
import AppIcon from './AppIcon.vue'
const { t } = useI18n(),
  session = useSession()
defineEmits<{ navigate: [] }>()
const groups = computed(() =>
  ['platform', 'team', 'departments']
    .map((key) => ({
      key,
      links: adminLinks.filter((link) => link.group === key && session.can(link.permission)),
    }))
    .filter((group) => group.links.length),
)
</script>
<template>
  <nav :aria-label="t('common.navigation')" class="sidebar-navigation">
    <section v-for="group in groups" :key="group.key">
      <h2>{{ t('navigation.' + group.key) }}</h2>
      <RouterLink
        v-for="link in group.links"
        :key="link.path"
        :to="link.path"
        :class="{ active: $route.path === link.path }"
        :aria-current="$route.path === link.path ? 'page' : undefined"
        @click="$emit('navigate')"
      >
        <AppIcon :name="link.icon" :size="19" />
        <span>{{ t('navigation.' + link.key) }}</span>
      </RouterLink>
    </section>
  </nav>
</template>
