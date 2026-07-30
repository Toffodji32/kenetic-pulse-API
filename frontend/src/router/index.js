import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '@/views/LoginView.vue'
import WalletView from '@/views/Dashboard/WalletView.vue'
import ShopView from '@/views/ShopView.vue'
import WithdrawalsAdmin from '@/views/SuperAdmin/WithdrawalsAdmin.vue'
import { useAuthStore } from '@/stores/auth'

const routes = [
  { path: '/', redirect: '/shop' },
  { path: '/login', name: 'login', component: LoginView },
  {
    path: '/shop',
    name: 'shop',
    component: ShopView,
    meta: { requiresAuth: true, requiresClient: true },
  },
  {
    path: '/wallet',
    name: 'wallet',
    component: WalletView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/superadmin/withdrawals',
    name: 'withdrawals-admin',
    component: WithdrawalsAdmin,
    meta: { requiresAuth: true, requiresSuperAdmin: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

function hasRole(user, role) {
  return user?.roles?.includes(role)
}

router.beforeEach((to, from, next) => {
  const auth = useAuthStore()

  if (to.path === '/login' && auth.token) {
    if (hasRole(auth.user, 'ROLE_CLIENT') && !hasRole(auth.user, 'ROLE_ADMIN') && !hasRole(auth.user, 'ROLE_SUPER_ADMIN')) {
      return next('/shop')
    }
    return next('/wallet')
  }

  if (to.meta.requiresAuth && !auth.token) {
    return next('/login')
  }

  if (to.meta.requiresSuperAdmin && !hasRole(auth.user, 'ROLE_SUPER_ADMIN')) {
    return next('/wallet')
  }

  if (to.meta.requiresAdmin && !hasRole(auth.user, 'ROLE_ADMIN') && !hasRole(auth.user, 'ROLE_SUPER_ADMIN')) {
    return next('/shop')
  }

  if (to.meta.requiresClient && !hasRole(auth.user, 'ROLE_CLIENT')) {
    return next('/wallet')
  }

  next()
})

export default router
