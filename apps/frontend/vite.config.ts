import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    host: '0.0.0.0',
    allowedHosts: ['frontend', 'localhost', 'proxy'],
    hmr: { clientPort: 8080 },
    proxy: { '/api': 'http://proxy' },
  },
  test: { environment: 'jsdom', include: ['src/**/*.test.ts'], restoreMocks: true },
})
