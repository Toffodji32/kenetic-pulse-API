import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

const API = axios.create({ baseURL: '/api' })

API.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

API.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export { API }

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('token'))
  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))

  const isAuthenticated = computed(() => !!token.value)
  const isSuperAdmin = computed(() => user.value?.roles?.includes('ROLE_SUPER_ADMIN'))
  const isAdmin = computed(() => user.value?.roles?.includes('ROLE_ADMIN') || user.value?.roles?.includes('ROLE_SUPER_ADMIN'))
  const isClient = computed(() => user.value?.roles?.includes('ROLE_CLIENT') && !isAdmin.value)

  function setAuth(t, u) {
    token.value = t
    user.value = u
    localStorage.setItem('token', t)
    localStorage.setItem('user', JSON.stringify(u))
  }

  async function login(email, password) {
    const { data } = await axios.post('/api/login', { email, password })
    setAuth(data.token, data.user)
    return data
  }

  function logout() {
    token.value = null
    user.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('user')
  }

  return { token, user, isAuthenticated, isSuperAdmin, isAdmin, isClient, setAuth, login, logout }
})
