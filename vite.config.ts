/// <reference types="vitest" />

import legacy from '@vitejs/plugin-legacy'
import vue from '@vitejs/plugin-vue'
import path from 'path'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [
    vue(),
    legacy()
  ],
  server: {
    proxy: {
      '/6IS/backend': {
        target: 'http://localhost',
        changeOrigin: true
      }
    }
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './frontend/src'),
    },
  },
  build: {
    outDir: './frontend/dist',
    emptyOutDir: true
  },
  test: {
    globals: true,
    environment: 'jsdom'
  }
})
