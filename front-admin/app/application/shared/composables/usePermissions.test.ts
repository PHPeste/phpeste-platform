import { beforeEach, describe, expect, it, vi } from 'vitest'
import editionService from '@/modules/shell/services/editionService'
import { useEdition } from './useEdition'
import { usePermissions } from './usePermissions'

async function asRole(role: string) {
  vi.spyOn(editionService, 'list').mockResolvedValue({
    data: [{ id: 1, slug: 'ed-1', name: 'E', city: 'C', state: 'PB', year: 2027, site_url: null, role }],
  })
  await useEdition().load()
}

beforeEach(() => {
  vi.restoreAllMocks()
  useEdition().reset()
})

describe('usePermissions', () => {
  it('financeiro enxerga vendas e não enxerga CFP', async () => {
    await asRole('financeiro')
    const { can, canOpen, visibleItems } = usePermissions()
    expect(can('orders')).toBe(true)
    expect(can('cfp')).toBe(false)
    expect(canOpen('/pedidos/10')).toBe(true)
    expect(canOpen('/cfp')).toBe(false)
    expect(visibleItems.value.some((i) => i.key === 'cfp')).toBe(false)
  })

  it('sem edição carregada nada é permitido', () => {
    const { can, visibleItems } = usePermissions()
    expect(can('overview')).toBe(false)
    expect(visibleItems.value).toHaveLength(0)
  })

  it('rota fora do menu não é bloqueada por papel', async () => {
    await asRole('voluntario')
    expect(usePermissions().canOpen('/qualquer-coisa')).toBe(true)
  })
})
