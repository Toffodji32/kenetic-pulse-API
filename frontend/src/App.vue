<template>
  <div id="kinetic-app">
    <nav v-if="isAdmin" class="nav">
      <router-link to="/wallet" class="nav-link">Wallet</router-link>
      <router-link v-if="isSuperAdmin" to="/superadmin/withdrawals" class="nav-link">Retraits</router-link>
      <button @click="logout" class="btn-logout">Déconnexion</button>
    </nav>
    <nav v-else-if="isLoggedIn" class="nav">
      <router-link to="/client" class="nav-link">Mon espace</router-link>
      <button @click="logout" class="btn-logout">Déconnexion</button>
    </nav>
    <main class="main">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const isLoggedIn = computed(() => !!auth.token)
const isAdmin = computed(() => auth.isAdmin)
const isSuperAdmin = computed(() => auth.user?.roles?.includes('ROLE_SUPER_ADMIN'))

function logout() {
  auth.logout()
  router.push('/login')
}
</script>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; color: #333; }
.nav { display: flex; align-items: center; gap: 1rem; padding: 1rem 2rem; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.nav-link { color: #4f46e5; text-decoration: none; font-weight: 500; }
.nav-link:hover { text-decoration: underline; }
.btn-logout { margin-left: auto; padding: .5rem 1rem; border: none; border-radius: 6px; background: #ef4444; color: #fff; cursor: pointer; }
.main { max-width: 1200px; margin: 0 auto; padding: 2rem; }
.card { background: #fff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
</style>
