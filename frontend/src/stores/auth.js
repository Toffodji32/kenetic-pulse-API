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

  async function refreshUser() {
    if (!token.value) return
    try {
      const { data } = await API.get('/me')
      user.value = data
      localStorage.setItem('user', JSON.stringify(data))
    } catch {
      logout()
    }
  }

  return { token, user, isAuthenticated, isSuperAdmin, setAuth, login, logout, refreshUser }
})
