import { createRouter, createWebHistory } from 'vue-router'
import { useSession } from '../stores/session'
import AuthView from '../views/AuthView.vue'
import HomeView from '../views/HomeView.vue'
import CustomerView from '../views/CustomerView.vue'
import AdminView from '../views/AdminView.vue'
import ErrorView from '../views/ErrorView.vue'
import { adminLinks } from '../app/navigation'
export { adminLinks }
export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: HomeView },
    ...[
      'login',
      'register',
      'verify-email',
      'forgot-password',
      'reset-password',
      'accept-invitation',
    ].map((name) => ({ path: '/' + name, component: AuthView })),
    ...['/app', '/app/profile', '/app/settings'].map((path) => ({
      path,
      component: CustomerView,
      meta: { authenticated: true, customer: true },
    })),
    ...adminLinks.map((link) => ({
      path: link.path,
      component: AdminView,
      meta: { authenticated: true, permission: link.permission },
    })),
    { path: '/forbidden', component: ErrorView },
    { path: '/:pathMatch(.*)*', component: ErrorView },
  ],
})
router.beforeEach(async (to) => {
  const session = useSession()
  try {
    if (!session.initialized) await session.refresh()
  } catch {
    return to.path === '/forbidden' ? true : '/forbidden'
  }
  if (to.meta.authenticated && !session.user)
    return { path: '/login', query: { redirect: to.path } }
  if (to.meta.permission && !session.can(String(to.meta.permission))) return '/forbidden'
  if (to.meta.customer && session.user?.kind !== 'customer') return '/forbidden'
})
export function landingFor(permissions: string[], kind: string): string {
  return kind === 'customer'
    ? '/app'
    : (adminLinks.find((link) => permissions.includes(link.permission))?.path ?? '/forbidden')
}
