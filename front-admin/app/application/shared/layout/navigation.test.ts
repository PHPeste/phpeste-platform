import { describe, expect, it } from 'vitest'
import { canSee, findNavItem, NAV_GROUPS, NAV_ITEMS } from './navigation'
import locale from '@/modules/shell/locales/pt-br'

describe('navegação do painel', () => {
  it('segue os grupos e a ordem do design', () => {
    expect(NAV_GROUPS).toEqual(['edition', 'content', 'sales', 'organization', 'system'])
    expect(NAV_ITEMS.map((i) => i.key)).toEqual([
      'overview', 'identity', 'team', 'cfp', 'review', 'speakers', 'schedule', 'tickets', 'orders',
      'payments', 'checkin', 'sponsors', 'suppliers', 'finance', 'tasks', 'comms', 'settings',
    ])
  })

  it('todo item tem rótulo e rota únicos', () => {
    for (const item of NAV_ITEMS) expect(locale.nav[item.key]).toBeTruthy()
    expect(new Set(NAV_ITEMS.map((i) => i.to)).size).toBe(NAV_ITEMS.length)
  })

  it('organizador vê tudo; papel desconhecido não vê nada', () => {
    expect(NAV_ITEMS.every((i) => canSee(i, 'organizador'))).toBe(true)
    expect(NAV_ITEMS.some((i) => canSee(i, null))).toBe(false)
  })

  it.each([
    ['financeiro', ['overview', 'tickets', 'orders', 'payments', 'finance']],
    ['avaliador', ['overview', 'review']],
    ['checkin', ['overview', 'checkin']],
    ['voluntario', ['overview', 'checkin']],
  ] as const)('papel %s só vê os itens permitidos', (role, keys) => {
    expect(NAV_ITEMS.filter((i) => canSee(i, role)).map((i) => i.key)).toEqual(keys)
  })

  it('acha o item mais específico da rota', () => {
    expect(findNavItem('/')?.key).toBe('overview')
    expect(findNavItem('/cfp')?.key).toBe('cfp')
    expect(findNavItem('/cfp/avaliacao')?.key).toBe('review')
    expect(findNavItem('/cfp/avaliacao/42')?.key).toBe('review')
    expect(findNavItem('/pedidos/')?.key).toBe('orders')
    expect(findNavItem('/inexistente')).toBeNull()
  })
})
