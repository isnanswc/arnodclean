import axios from 'axios'

// Determine base URL dynamically so it works on any path (localhost subfolder, root domain, mobile IP):
export function getBaseUrl(): string {
  if (import.meta.env.DEV) {
    return '/api'
  }
  // In production, detect current subpath before /admin-v2
  if (typeof window !== 'undefined') {
    const path = window.location.pathname
    const match = path.match(/^(.*?)\/admin-v2(?:\/.*)?$/)
    const prefix = match ? match[1] : ''
    return `${prefix}/api`
  }
  return '/api'
}

export const apiClient = axios.create({
  baseURL: getBaseUrl(),
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// Request interceptor to normalize paths (e.g. /api/v2/... -> /v2/...)
apiClient.interceptors.request.use((config) => {
  if (config.url && config.url.startsWith('/api/')) {
    config.url = config.url.substring(4)
  }
  return config
})

// Response interceptor for error handling
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    return Promise.reject(error)
  }
)

export default apiClient
