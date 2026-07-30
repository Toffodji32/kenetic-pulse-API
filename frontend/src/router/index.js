import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '@/views/LoginView.vue'
import WalletView from '@/views/Dashboard/WalletView.vue'
import WithdrawalsAdmin from '@/views/SuperAdmin/WithdrawalsAdmin.vue'
import { useAuthStore } from '@/stores/auth'

const routes = [
  { path: '/', redirect: '/wallet' },
  { path: '/login', name: 'login', component: LoginView },
  {
    path: '/wallet',
    name: 'wallet',
    component: WalletView,
    meta: { requiresAuth: true },
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

router.beforeEach((to, from, next) => {
  const auth = useAuthStore()
  if (to.path === '/login' && auth.token) {
    return next('/wallet')
  }
  if (to.meta.requiresAuth && !auth.token) {
    return next('/login')
  }
  if (to.meta.requiresSuperAdmin && !auth.user?.roles?.includes('ROLE_SUPER_ADMIN')) {
    return next('/wallet')
  }
  next()
})

export default router
