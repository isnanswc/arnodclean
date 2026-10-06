import { defineStore } from 'pinia'
import { ref } from 'vue'
import { authApi } from '@/api/auth'
import type { User } from '@/types/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const isAuthenticated = ref<boolean>(false)
  const isInitializing = ref<boolean>(true)
  const error = ref<string | null>(null)

  async function checkAuth() {
    isInitializing.value = true
    try {
      const res = await authApi.getMe()
      if (res.logged_in && res.user) {
        user.value = res.user
        isAuthenticated.value = true
      } else {
        user.value = null
        isAuthenticated.value = false
      }
    } catch (err) {
      user.value = null
      isAuthenticated.value = false
    } finally {
      isInitializing.value = false
    }
  }

  async function login(identity: string, password: string) {
    error.value = null
    try {
      const res = await authApi.login(identity, password)
      if (res.status === 'success' && res.user) {
        user.value = res.user
        isAuthenticated.value = true
        return true
      } else {
        error.value = res.message || 'Login gagal.'
        return false
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Email atau kata sandi tidak sesuai.'
      return false
    }
  }

  async function devLogin() {
    error.value = null
    try {
      const res = await authApi.devLogin()
      if (res.status === 'success' && res.user) {
        user.value = res.user
        isAuthenticated.value = true
        return true
      } else {
        error.value = res.message || 'Dev login gagal.'
        return false
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Dev login gagal dilakukan.'
      return false
    }
  }

  async function logout() {
    try {
      await authApi.logout()
    } finally {
      user.value = null
      isAuthenticated.value = false
    }
  }

  return {
    user,
    isAuthenticated,
    isInitializing,
    error,
    checkAuth,
    login,
    devLogin,
    logout,
  }
})
