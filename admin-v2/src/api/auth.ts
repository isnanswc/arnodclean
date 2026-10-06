import { apiClient } from './client'
import type { AuthResponse } from '@/types/auth'

export const authApi = {
  async getMe(): Promise<AuthResponse> {
    const res = await apiClient.get<AuthResponse>('/v2/auth.php?action=me')
    return res.data
  },

  async login(identity: string, password: string): Promise<AuthResponse> {
    const res = await apiClient.post<AuthResponse>('/v2/auth.php?action=login', {
      identity,
      password,
    })
    return res.data
  },

  async devLogin(): Promise<AuthResponse> {
    const res = await apiClient.post<AuthResponse>('/v2/auth.php?action=dev_login')
    return res.data
  },

  async logout(): Promise<AuthResponse> {
    const res = await apiClient.post<AuthResponse>('/v2/auth.php?action=logout')
    return res.data
  },
}
