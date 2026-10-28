<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import PublicHeader from '../components/PublicHeader.vue'
import AppIcon from '../components/AppIcon.vue'
import AppCard from '../components/AppCard.vue'
import AppBadge from '../components/AppBadge.vue'
import ShipIllustration from '../components/ShipIllustration.vue'
import BrandMark from '../components/BrandMark.vue'
const { t } = useI18n()
</script>
<template>
  <div class="public-page">
    <PublicHeader />
    <main id="main-content">
      <section class="landing-hero content-width">
        <div>
          <AppBadge tone="success">{{ t('home.eyebrow') }}</AppBadge>
          <h1>{{ t('home.title') }}</h1>
          <p class="hero-description">{{ t('home.description') }}</p>
          <RouterLink class="app-button button-primary hero-cta" to="/register">
            {{ t('auth.register') }}
            <AppIcon name="next" />
          </RouterLink>
          <p class="fine-print">{{ t('home.availability') }}</p>
        </div>
        <div class="hero-art">
          <span class="art-orbit"></span>
          <ShipIllustration />
          <div class="hero-brand-note">
            <AppIcon name="audit" :size="28" />
            <span>{{ t('brand.tagline') }}</span>
          </div>
        </div>
      </section>
      <section id="approach" class="landing-features content-width">
        <div class="section-heading">
          <span class="eyebrow">{{ t('home.approach') }}</span>
          <h2>{{ t('home.clarity') }}</h2>
        </div>
        <div class="feature-grid">
          <AppCard
            v-for="feature in [
              { key: 'account', icon: 'lock' },
              { key: 'team', icon: 'staff' },
              { key: 'future', icon: 'carriers' },
            ]"
            :key="feature.key"
          >
            <span class="feature-icon"><AppIcon :name="feature.icon" :size="26" /></span>
            <h3>{{ t('home.' + feature.key + 'Title') }}</h3>
            <p>{{ t('home.' + feature.key + 'Body') }}</p>
          </AppCard>
        </div>
      </section>
    </main>
    <footer class="site-footer content-width">
      <BrandMark />
      <span>{{ t('home.footer') }}</span>
    </footer>
  </div>
</template>
