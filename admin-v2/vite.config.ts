import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  base: '/admin-v2/',
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8080/arno-dc',
        changeOrigin: true,
      },
      '/uploads': {
        target: 'http://localhost:8080/arno-dc',
        changeOrigin: true,
      },
    },
  },
})
