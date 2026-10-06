export interface User {
  id: number
  username: string
  email: string
  role: 'admin' | 'editor'
  avatar: string
}

export interface AuthResponse {
  status: 'success' | 'error'
  message?: string
  logged_in?: boolean
  user?: User | null
}
