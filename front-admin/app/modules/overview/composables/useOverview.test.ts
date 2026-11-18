import { beforeEach, describe, expect, it, vi } from 'vitest'
import overviewService, { type OverviewPayload } from '../services/overviewService'
import { formatLongRange, formatShortDate, translate, useOverview } from './useOverview'

vi.mock('../services/overviewService', () => ({ default: { get: vi.fn() } }))

export const payload: OverviewPayload['data'] = {
  edition: { id: 'ed-27', name: 'PHPeste 2027', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB' },
  stage: {
    current: 'cfp_open',
    days_until_event: 339,
    steps: [
      { status: 'draft', label: 'Rascunho', note: 'Preparação', state: 'done' },
      { status: 'cfp_open', label: 'CFP aberto', note: 'Chamada de trabalhos', state: 'current' },
      { status: 'sales_open', label: 'Vendas abertas', note: 'Venda de ingressos', state: 'next' },
      { status: 'ongoing', label: 'Em andamento', note: 'Evento', state: 'upcoming' },
      { status: 'closed', label: 'Encerrada', note: 'Pós-evento', state: 'upcoming' },
    ],
  },
  indicators: { tickets: null, revenue: null, proposals: { total: 47, reviewed: 31 }, speakers: { confirmed: 6, goal: 24 } },
  sections: { tickets_per_week: null, nordeste: { base: 'confirmed', people: 8, percent: 62, quota: 50, require_quota: true, missing_states: [{ uf: 'AL', name: 'Alagoas' }, { uf: 'SE', name: 'Sergipe' }] } },
  pending: [{ key: 'proposals_without_reviewer', title: '7 propostas sem revisor', tone: 'warn', tag: 'Atenção', action_label: 'Atribuir', action_path: '/cfp/avaliacao?filtro=sem-revisor' }],
  milestones: [{ key: 'cfp_closes', title: 'Fechamento do CFP', description: 'Propostas deixam de ser aceitas', date: '2027-04-15' }],
}

beforeEach(() => vi.clearAllMocks())

describe('translate', () => {
  it('monta o subtítulo e os indicadores, sem inventar número de vendas', () => {
    const overview = translate(payload)

    expect(overview.subtitle).toBe('PHPeste 2027 · 8 e 9 de outubro de 2027 · João Pessoa (PB)')
    expect(overview.indicators).toEqual([
      { label: 'Ingressos vendidos', value: '—', hint: 'as vendas ainda não começaram' },
      { label: 'Receita bruta', value: '—', hint: 'as vendas ainda não começaram' },
      { label: 'Propostas no CFP', value: '47', hint: '31 já avaliadas' },
      { label: 'Palestrantes confirmados', value: '6', hint: 'meta de 24 na grade' },
    ])
    expect(overview.nordeste?.missingStates).toEqual(['Alagoas', 'Sergipe'])
    expect(overview.pending[0]).toMatchObject({ actionPath: '/cfp/avaliacao?filtro=sem-revisor', tone: 'warn' })
  })

  it('formata vendas e receita quando existirem', () => {
    const overview = translate({ ...payload, indicators: { ...payload.indicators, tickets: { sold: 128, available: 400 }, revenue: { gross: 1920000, net: 1850000 } }, sections: { ...payload.sections, tickets_per_week: [12, 18] } })

    expect(overview.indicators[0]).toEqual({ label: 'Ingressos vendidos', value: '128', hint: '128 de 400 disponíveis' })
    expect(overview.indicators[1]!.value.replace(/\s/g, ' ')).toBe('R$ 19.200')
    expect(overview.ticketsPerWeek).toEqual([{ label: 'sem 1', value: 12 }, { label: 'sem 2', value: 18 }])
  })

  it('formata datas', () => {
    expect(formatShortDate('2027-04-15')).toBe('15 abr 2027')
    expect(formatLongRange('2027-10-08', '2027-10-10')).toBe('8 a 10 de outubro de 2027')
    expect(formatLongRange(null, null)).toBeNull()
  })
})

describe('useOverview', () => {
  it('carrega a visão geral da edição numa chamada só', async () => {
    vi.mocked(overviewService.get).mockResolvedValueOnce({ data: payload })

    const { load, state, overview } = useOverview()
    await load('ed-27')

    expect(overviewService.get).toHaveBeenCalledTimes(1)
    expect(overviewService.get).toHaveBeenCalledWith('ed-27')
    expect(state.value).toBe('ready')
    expect(overview.value?.daysUntilEvent).toBe(339)
  })

  it('marca erro quando falha', async () => {
    vi.mocked(overviewService.get).mockRejectedValueOnce(new Error('offline'))

    const { load, state } = useOverview()
    await load('ed-27')

    expect(state.value).toBe('error')
  })
})
