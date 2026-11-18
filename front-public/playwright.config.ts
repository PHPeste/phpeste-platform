import { fileURLToPath } from 'node:url'
import { defineConfig, devices } from '@playwright/test'
import { MOCK_API_PORT } from './e2e/support/mockApi'

process.env.NUXT_API_INTERNAL_URL = `http://127.0.0.1:${MOCK_API_PORT}/api/v1`
process.env.NUXT_TRUST_FORWARDED_HOST = 'true'
process.env.NUXT_EDITION_CACHE_SECONDS = '1'

// @nuxt/test-utils/playwright's `goto` fixture boots an ephemeral Nuxt
// server per worker (via the `nuxt` option below) — no separate `npm run
// dev` needed in another terminal, `npm run test:e2e` just works.
export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/support/globalSetup.ts',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: 'list',
  use: {
    nuxt: {
      rootDir: fileURLToPath(new URL('.', import.meta.url)),
    },
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
})
