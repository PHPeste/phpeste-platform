import type { EditionRole } from '@/modules/shell/types'
import { canSee, findNavItem } from './navigation'

export type AccessDecision =
  | { type: 'allow' }
  | { type: 'login'; redirect: string | null }
  | { type: 'home' }
  | { type: 'forbidden' }

interface AccessInput {
  isAuthenticated: boolean
  isPublicPage: boolean
  path: string
  fullPath: string
  role: EditionRole | null
}

/** Decide o que fazer ao entrar numa rota. Pura: o middleware só aplica o resultado. */
export function decideAccess(input: AccessInput): AccessDecision {
  if (!input.isAuthenticated) {
    if (input.isPublicPage) return { type: 'allow' }
    return { type: 'login', redirect: input.fullPath === '/' ? null : input.fullPath }
  }

  if (input.path === '/login') return { type: 'home' }
  if (input.isPublicPage) return { type: 'allow' }

  // Rota fora do menu cai no 404 do Nuxt. A visão geral abre para qualquer papel,
  // mesmo sem edição carregada.
  const item = findNavItem(input.path)
  if (item && item.key !== 'overview' && !canSee(item, input.role)) return { type: 'forbidden' }
  return { type: 'allow' }
}
