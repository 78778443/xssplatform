import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  workers: 1,
  retries: 0,
  use: {
    baseURL: process.env.XSSP_BASE_URL ?? 'http://127.0.0.1:18081',
    locale: 'zh-CN',
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2,
    colorScheme: 'light',
  },
  projects: [
    {
      name: 'desktop',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 },
    },
  ],
})
