import { describe, expect, it } from 'vitest'
import { decideAccess } from './access'

const base = { isAuthenticated: true, isPublicPage: false, path: '/', fullPath: '/', role: 'organizador' as const }

describe('decideAccess', () => {
  it('sem token manda ao login guardando a rota de origem (com query)', () => {
    expect(decideAccess({ ...base, isAuthenticated: false, path: '/pedidos', fullPath: '/pedidos?status=pago' }))
      .toEqual({ type: 'login', redirect: '/pedidos?status=pago' })
  })

  it('sem token na raiz não guarda origem', () => {
    expect(decideAccess({ ...base, isAuthenticated: false })).toEqual({ type: 'login', redirect: null })
  })

  it('página pública abre sem token', () => {
    expect(decideAccess({ ...base, isAuthenticated: false, isPublicPage: true, path: '/login' })).toEqual({ type: 'allow' })
  })

  it('logado que abre o login volta para a raiz', () => {
    expect(decideAccess({ ...base, isPublicPage: true, path: '/login' })).toEqual({ type: 'home' })
  })

  it('papel permitido abre; não permitido recebe 403', () => {
    expect(decideAccess({ ...base, role: 'financeiro', path: '/pedidos' })).toEqual({ type: 'allow' })
    expect(decideAccess({ ...base, role: 'financeiro', path: '/cfp' })).toEqual({ type: 'forbidden' })
    expect(decideAccess({ ...base, role: 'avaliador', path: '/cfp/avaliacao/3' })).toEqual({ type: 'allow' })
    expect(decideAccess({ ...base, role: 'avaliador', path: '/cfp' })).toEqual({ type: 'forbidden' })
  })

  it('sem papel conhecido só a visão geral abre', () => {
    expect(decideAccess({ ...base, role: null, path: '/' })).toEqual({ type: 'allow' })
    expect(decideAccess({ ...base, role: null, path: '/pedidos' })).toEqual({ type: 'forbidden' })
  })

  it('rota fora do menu segue para o 404 do Nuxt', () => {
    expect(decideAccess({ ...base, role: 'voluntario', path: '/nao-existe' })).toEqual({ type: 'allow' })
  })
})
