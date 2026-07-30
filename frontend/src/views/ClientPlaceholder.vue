<template>
  <div class="client-page">
    <div class="card">
      <h1>Espace Client</h1>
      <p>Bienvenue sur votre espace client. La boutique est accessible depuis l'application mobile ou le site public.</p>
      <button @click="logout" class="btn btn-secondary">Déconnexion</button>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

function logout() {
  auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.client-page {
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
}
.card {
  text-align: center;
  max-width: 400px;
}
.card h1 { margin-bottom: 1rem; }
.card p { color: #6b7280; margin-bottom: 1.5rem; }
</style>
