import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@pinia/nuxt', '@nuxt/eslint'],
  css: ['~/assets/css/main.css'],
  app: {
    head: {
      htmlAttrs: { lang: 'pt-BR' },
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
      ],
    },
  },
  routeRules: {
    '/palestrante/**': { ssr: false },
    '/conta/**': { ssr: false },
    '/ingressos/**': { ssr: false },
    '/pedidos/**': { ssr: false },
    '/**': { headers: { 'Cache-Control': 'private, no-store' } },
  },
  vite: {
    plugins: [tailwindcss()],
  },
  runtimeConfig: {
    apiInternalUrl: process.env.NUXT_API_INTERNAL_URL || process.env.NUXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1',
    trustForwardedHost: process.env.NUXT_TRUST_FORWARDED_HOST === 'true',
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1',
    },
  },
})
