<template>
  <div class="login-page">
    <div class="login-card card">
      <h1>Kinetic Pulse</h1>
      <p class="subtitle">Connexion à votre espace</p>

      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label for="email">Email</label>
          <input id="email" v-model="email" type="email" required placeholder="admin@gym.com" />
        </div>

        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input id="password" v-model="password" type="password" required placeholder="••••••••" />
        </div>

        <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
          {{ loading ? 'Connexion...' : 'Se connecter' }}
        </button>

        <div v-if="error" class="alert alert-error">{{ error }}</div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function handleLogin() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    router.push(auth.isAdmin ? '/wallet' : '/client')
  } catch (e) {
    error.value = e.response?.data?.error || 'Email ou mot de passe incorrect'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
}
.login-card {
  width: 100%;
  max-width: 400px;
  padding: 2.5rem;
  text-align: center;
}
.login-card h1 {
  font-size: 1.5rem;
  margin-bottom: .25rem;
}
.subtitle {
  color: #6b7280;
  margin-bottom: 1.5rem;
  font-size: .9rem;
}
.form-group {
  margin-bottom: 1rem;
  text-align: left;
}
.form-group label {
  display: block;
  font-size: .85rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: .25rem;
}
.form-group input {
  width: 100%;
  padding: .7rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: .9rem;
}
.btn-block {
  width: 100%;
  margin-top: .5rem;
}
.alert {
  margin-top: 1rem;
}
</style>
