<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, type Dashboard, type Department } from '../api/client'
import { useSession } from '../stores/session'
import { useFeedback } from '../composables/useFeedback'
import { adminLinks } from '../app/navigation'
import { translatedCode, formatDate, formatNumber } from '../i18n'
import BrandMark from '../components/BrandMark.vue'
import LanguageSwitcher from '../components/LanguageSwitcher.vue'
import UserMenu from '../components/UserMenu.vue'
import SidebarNavigation from '../components/SidebarNavigation.vue'
import PageHeader from '../components/PageHeader.vue'
import AppCard from '../components/AppCard.vue'
import AppBadge from '../components/AppBadge.vue'
import AppIcon from '../components/AppIcon.vue'
import AppModal from '../components/AppModal.vue'
import AppSkeleton from '../components/AppSkeleton.vue'
import AppEmptyState from '../components/AppEmptyState.vue'
import FeedbackBox from '../components/FeedbackBox.vue'
import ShipIllustration from '../components/ShipIllustration.vue'
import StaffManager from '../features/administration/StaffManager.vue'
import RoleMatrix from '../features/administration/RoleMatrix.vue'
import AuditTable from '../features/administration/AuditTable.vue'
import SupportLookup from '../features/administration/SupportLookup.vue'
const session = useSession(),
  route = useRoute(),
  { t } = useI18n(),
  open = ref(false),
  loading = ref(false),
  { failure, errorText, clear } = useFeedback()
const active = computed(() => adminLinks.find((link) => link.path === route.path)),
  overview = ref<Dashboard | null>(null),
  department = ref<Department | null>(null)
const areas: Record<string, string[]> = {
  finance: ['revenue', 'expenses', 'refunds'],
  marketing: ['campaigns', 'conversion'],
  operations: ['shipments', 'fulfillment'],
  support: ['cases'],
  carriers: ['providers', 'configuration'],
  qa: ['tests', 'issues'],
}
const accountTotal = computed(
  () => overview.value?.accountStatuses.reduce((sum, row) => sum + Number(row.count), 0) || 0,
)
const pending = computed(() =>
  Number(
    overview.value?.accountStatuses.find((row) => row.status === 'pending_verification')?.count ||
      0,
  ),
)
const chartStyle = computed(() => {
  if (!accountTotal.value || !overview.value) return { background: '#eef2f6' }
  const colors: Record<string, string> = {
    active: '#159573',
    pending_verification: '#f2c45c',
    suspended: '#eb7c82',
    invited: '#96acc4',
  }
  let start = 0
  return {
    background:
      'conic-gradient(' +
      overview.value.accountStatuses
        .map((row) => {
          const end = start + (Number(row.count) / accountTotal.value) * 100
          const part = `${colors[row.status] || '#96acc4'} ${start}% ${end}%`
          start = end
          return part
        })
        .join(',') +
      ')',
  }
})
let generation = 0
watch(
  () => route.path,
  async (path) => {
    const current = ++generation
    clear()
    overview.value = null
    department.value = null
    open.value = false
    loading.value = true
    try {
      if (path === '/admin') {
        const data = await api<Dashboard>('/admin/dashboard')
        if (current === generation) overview.value = data
      } else if (areas[path.split('/')[2]]) {
        const data = await api<Department>('/admin/departments/' + path.split('/')[2])
        if (current === generation) department.value = data
      }
    } catch (e) {
      if (current === generation) failure.value = e
    } finally {
      if (current === generation) loading.value = false
    }
  },
  { immediate: true },
)
</script>
<template>
  <div class="admin-shell">
    <a href="#main-content" class="skip-link">{{ t('common.skip') }}</a>
    <aside class="sidebar">
      <BrandMark />
      <SidebarNavigation />
      <div class="sidebar-brand-card">
        <strong>
          ShipWise
          <span>AI</span>
        </strong>
        <p>{{ t('brand.tagline') }}</p>
        <ShipIllustration />
      </div>
    </aside>
    <AppModal v-model:open="open" :title="t('common.workspace')" drawer side="left">
      <SidebarNavigation @navigate="open = false" />
    </AppModal>
    <div class="admin-body">
      <header class="admin-topbar">
        <button
          class="icon-button mobile-nav-toggle"
          type="button"
          :aria-label="t('common.openNavigation')"
          :aria-expanded="open"
          @click="open = true"
        >
          <AppIcon name="menu" />
        </button>
        <nav class="breadcrumbs" :aria-label="t('common.workspace')">
          <span>{{ t('common.workspace') }}</span>
          <AppIcon name="right" :size="14" />
          <strong>{{ t('navigation.' + active?.key) }}</strong>
        </nav>
        <div class="header-actions">
          <LanguageSwitcher />
          <UserMenu />
        </div>
      </header>
      <main id="main-content" class="admin-main">
        <PageHeader
          :title="t('navigation.' + active?.key)"
          :description="
            t(
              route.path === '/admin'
                ? 'admin.description'
                : route.path === '/admin/audit'
                  ? 'audit.description'
                  : route.path === '/admin/staff'
                    ? 'staff.description'
                    : route.path === '/admin/access-control'
                      ? 'access.description'
                      : 'departments.description',
            )
          "
          :icon="active?.icon"
        />
        <FeedbackBox :error="errorText" />
        <AppSkeleton v-if="loading" />
        <template v-if="overview">
          <div class="metric-grid">
            <AppCard class="metric-card">
              <span class="feature-icon"><AppIcon name="staff" :size="25" /></span>
              <div>
                <p>{{ t('admin.customers') }}</p>
                <strong>{{ formatNumber(overview.customerCount) }}</strong>
                <small>{{ t('admin.allCustomers') }}</small>
              </div>
            </AppCard>
            <AppCard class="metric-card">
              <span class="feature-icon"><AppIcon name="user" :size="25" /></span>
              <div>
                <p>{{ t('admin.staff') }}</p>
                <strong>{{ formatNumber(overview.activeStaffCount) }}</strong>
                <small>{{ t('admin.activeStaff') }}</small>
              </div>
            </AppCard>
            <AppCard class="metric-card">
              <span class="feature-icon icon-warning"><AppIcon name="audit" :size="25" /></span>
              <div>
                <p>{{ t('admin.pending') }}</p>
                <strong>{{ formatNumber(pending) }}</strong>
                <small>{{ t('statuses.pending_verification') }}</small>
              </div>
            </AppCard>
            <AppCard class="metric-card">
              <span class="feature-icon icon-blue"><AppIcon name="file" :size="25" /></span>
              <div>
                <p>{{ t('admin.auditCount') }}</p>
                <strong>
                  {{ overview.recentAudit ? formatNumber(overview.recentAudit.length) : '—' }}
                </strong>
                <small>{{ t('admin.recentActivity') }}</small>
              </div>
            </AppCard>
          </div>
          <div class="overview-grid">
            <AppCard :title="t('admin.recentActivity')">
              <template #header>
                <RouterLink v-if="session.can('audit.read')" class="text-link" to="/admin/audit">
                  {{ t('admin.viewAudit') }}
                  <AppIcon name="arrow" :size="16" />
                </RouterLink>
              </template>
              <ul v-if="overview.recentAudit?.length" class="activity-list">
                <li v-for="event in overview.recentAudit.slice(0, 5)" :key="event.id">
                  <span
                    class="event-icon"
                    :class="{ 'event-warning': event.action.includes('failed') }"
                  >
                    <AppIcon
                      :name="event.action.includes('failed') ? 'warning' : 'audit'"
                      :size="19"
                    />
                  </span>
                  <div>
                    <strong>{{ translatedCode('events', event.action) }}</strong>
                    <small>{{ formatDate(event.created_at) }} · {{ t('common.timezone') }}</small>
                  </div>
                  <AppBadge :tone="event.action.includes('failed') ? 'warning' : 'success'">
                    {{
                      t(event.action.includes('failed') ? 'common.attention' : 'common.recorded')
                    }}
                  </AppBadge>
                </li>
              </ul>
              <AppEmptyState
                v-else
                :title="t('admin.noActivity')"
                :description="t('admin.noActivityBody')"
                icon="audit"
              />
            </AppCard>
            <AppCard :title="t('admin.accountStatuses')">
              <div class="status-chart">
                <div class="donut" :style="chartStyle" aria-hidden="true">
                  <span>
                    <strong>{{ formatNumber(accountTotal) }}</strong>
                    <small>{{ t('common.account') }}</small>
                  </span>
                </div>
                <dl>
                  <div v-for="row in overview.accountStatuses" :key="row.status">
                    <dt>
                      <span :class="['status-dot', 'status-' + row.status]"></span>
                      {{ translatedCode('statuses', row.status) }}
                    </dt>
                    <dd>{{ formatNumber(row.count) }}</dd>
                  </div>
                </dl>
              </div>
              <RouterLink v-if="session.can('staff.read')" class="text-link" to="/admin/staff">
                {{ t('admin.manageTeam') }}
                <AppIcon name="right" :size="17" />
              </RouterLink>
            </AppCard>
          </div>
          <AppCard class="roadmap-card">
            <span class="feature-icon"><AppIcon name="carriers" :size="30" /></span>
            <div>
              <h2>{{ t('admin.roadmapTitle') }}</h2>
              <p>{{ t('admin.roadmapBody') }}</p>
            </div>
            <AppBadge>{{ t('common.future') }}</AppBadge>
          </AppCard>
          <p class="system-status">
            <span class="status-dot status-active"></span>
            {{ t('admin.available') }} ·
            {{ overview.health.version }}
          </p>
        </template>
        <StaffManager v-if="route.path === '/admin/staff'" />
        <RoleMatrix v-if="route.path === '/admin/access-control'" />
        <AuditTable v-if="route.path === '/admin/audit'" />
        <template v-if="department">
          <AppCard v-if="department.department === 'qa'" :title="t('departments.release')">
            <dl class="release-grid">
              <div>
                <dt>{{ t('departments.version') }}</dt>
                <dd>{{ department.version }}</dd>
              </div>
              <div>
                <dt>{{ t('departments.environment') }}</dt>
                <dd>{{ department.environment }}</dd>
              </div>
              <div>
                <dt>{{ t('departments.health') }}</dt>
                <dd>
                  <AppBadge tone="success">{{ t('admin.available') }}</AppBadge>
                </dd>
              </div>
            </dl>
            <p class="muted">{{ t('departments.qaNotice') }}</p>
          </AppCard>
          <AppCard v-if="department.department === 'marketing'" :title="t('departments.growth')">
            <h3>
              {{
                t(
                  'departments.customerCount',
                  {
                    count: formatNumber(department.customerCount || 0),
                  },
                  department.customerCount || 0,
                )
              }}
            </h3>
            <p class="muted">{{ t('departments.aggregate') }}</p>
          </AppCard>
          <SupportLookup
            v-if="department.department === 'support' && session.can('customers.read_limited')"
          />
          <div class="feature-grid department-grid">
            <AppCard v-for="area in areas[department.department]" :key="area">
              <AppEmptyState
                :title="t('departments.' + area)"
                :description="t('departments.futureBody')"
                :icon="active?.icon"
              >
                <AppBadge>{{ t('common.notAvailable') }}</AppBadge>
              </AppEmptyState>
            </AppCard>
          </div>
        </template>
        <footer class="workspace-footer">
          <span>{{ t('brand.foundation') }}</span>
          <span>ShipWise AI · {{ t('common.foundation') }}</span>
        </footer>
      </main>
    </div>
  </div>
</template>
