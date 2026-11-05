import tailwindcss from '@tailwindcss/vite'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@pinia/nuxt', '@nuxt/eslint'],
  css: ['~/assets/css/main.css'],
  // Kit de UI do tema Sertão: <PhButton>, <PhInput>, <PhCard>… (ver app/application/shared/components/ui)
  components: [
    { path: '~/application/shared/components/ui', pathPrefix: false, prefix: 'Ph' },
    { path: '~/components', pathPrefix: false },
  ],
  vite: {
    plugins: [tailwindcss()]
  },
  // apps/backend's API base URL — this app never talks to a database or a
  // Laravel packages/* module directly, only HTTP. See .claude/rules/architecture.md.
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1',
      // Libera a página /_dev/componentes fora do modo dev (só os testes e2e ligam).
      uiCatalog: false
    }
  }
})
