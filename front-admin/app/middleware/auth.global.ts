import { decideAccess } from '@/application/shared/layout/access'
import { useAuthStore } from '@/stores/authStore'
import { useEdition } from '@/application/shared/composables/useEdition'

// A sessão vive no navegador (localStorage), então a guarda só roda no cliente.
// Páginas públicas declaram `definePageMeta({ auth: false })`.
export default defineNuxtRouteMiddleware(async (to) => {
  if (import.meta.server) return

  const auth = useAuthStore()
  const edition = useEdition()
  const isPublicPage = to.meta.auth === false

  // O papel só é necessário em páginas protegidas.
  if (auth.isAuthenticated && !isPublicPage) await edition.ensureLoaded()

  const decision = decideAccess({
    isAuthenticated: auth.isAuthenticated,
    isPublicPage,
    path: to.path,
    fullPath: to.fullPath,
    role: edition.role.value,
  })

  if (decision.type === 'login') {
    return navigateTo({ path: '/login', query: decision.redirect ? { redirect: decision.redirect } : undefined })
  }
  if (decision.type === 'home') return navigateTo('/')
  if (decision.type === 'forbidden') throw createError({ statusCode: 403, statusMessage: 'Sem permissão' })
})

declare module '#app' {
  interface PageMeta {
    /** `false` libera a página para quem não está logado (ex.: login). */
    auth?: boolean
  }
}
