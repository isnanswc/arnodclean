import axios from 'axios'

// Determine base URL:
// In dev: proxy handles /api
// In prod under Apache: /arno-dc/api
const baseURL = import.meta.env.PROD ? '/arno-dc/api' : '/api'

export const apiClient = axios.create({
  baseURL,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// Response interceptor for error handling
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    return Promise.reject(error)
  }
)
