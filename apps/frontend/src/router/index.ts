import { createRouter, createWebHistory } from 'vue-router'
import { useSession } from '../stores/session'
import AuthView from '../views/AuthView.vue'
import HomeView from '../views/HomeView.vue'
import CustomerView from '../views/CustomerView.vue'
import ErrorView from '../views/ErrorView.vue'
export const adminLinks = [
  { path: '/admin', label: 'Overview', permission: 'platform.dashboard.view', symbol: '◫' },
  { path: '/admin/staff', label: 'Team members', permission: 'staff.read', symbol: '♧' },
  { path: '/admin/access-control', label: 'Access control', permission: 'roles.read', symbol: '◇' },
  { path: '/admin/operations', label: 'Operations', permission: 'operations.dashboard.view', symbol: '⇄' },
  { path: '/admin/finance', label: 'Finance', permission: 'finance.dashboard.view', symbol: '€' },
  { path: '/admin/marketing', label: 'Marketing', permission: 'marketing.dashboard.view', symbol: '↗' },
  { path: '/admin/support', label: 'Customer support', permission: 'support.dashboard.view', symbol: '◉' },
  { path: '/admin/carriers', label: 'Carriers', permission: 'carriers.dashboard.view', symbol: '▱' },
  { path: '/admin/qa', label: 'Quality assurance', permission: 'qa.dashboard.view', symbol: '✓' },
  { path: '/admin/audit', label: 'Audit trail', permission: 'audit.read', symbol: '≡' },
]
export const router = createRouter({ history: createWebHistory(), routes: [
  { path: '/', component: HomeView },
  ...['login','register','verify-email','forgot-password','reset-password','accept-invitation'].map(name => ({ path: '/'+name, component: AuthView })),
  ...['/app','/app/profile','/app/settings'].map(path => ({ path, component: CustomerView, meta: { authenticated: true, customer: true } })),
  ...adminLinks.map(link => ({ path: link.path, component: ErrorView, meta: { authenticated: true, permission: link.permission } })),
  { path: '/forbidden', component: ErrorView },
  { path: '/:pathMatch(.*)*', component: ErrorView },
] })
router.beforeEach(async to => {
  const session = useSession()
  try { if (!session.initialized) await session.refresh() } catch { return to.path === '/forbidden' ? true : '/forbidden' }
  if (to.meta.authenticated && !session.user) return { path: '/login', query: { redirect: to.path } }
  if (to.meta.permission && !session.can(String(to.meta.permission))) return '/forbidden'
  if (to.meta.customer && session.user?.kind !== 'customer') return '/forbidden'
})
export function landingFor(permissions: string[], kind: string): string { return kind === 'customer' ? '/app' : adminLinks.find(link => permissions.includes(link.permission))?.path ?? '/forbidden' }
