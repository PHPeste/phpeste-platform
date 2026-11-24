const money = (amount: number) => ({ amount, currency: 'BRL' })

export const cardPayload = (id: string, name: string, stage: string, extra: Record<string, unknown> = {}) => ({
  id, sponsor: { id: `s-${id}`, name }, tier: { id: 't-ouro', name: 'Ouro' }, stage, stage_label: stage, value: money(800_000), received: money(0), outstanding: money(800_000),
  owner: { id: 'u-1', name: 'Mariana Sales' }, lost_reason: null, position: 0, closed_at: null, paid_at: null, lost_at: null, created_at: '2027-03-01T12:00:00Z', last_contact: null, ...extra,
})

export const boardPayload = () => ({
  data: {
    indicators: { goal: money(6_000_000), committed: money(1_450_000), received: money(150_000), pipeline: money(1_600_000), deliverables_overdue: 2 },
    columns: [
      { stage: 'prospecting', label: 'Prospecção', count: 1, value: money(800_000), cards: [cardPayload('d-1', 'Hospedagem Norte', 'prospecting', { last_contact: { channel: 'email', channel_label: 'E-mail', summary: 'Contato feito', happened_at: '2027-03-05T12:00:00Z' } })] },
      { stage: 'negotiating', label: 'Negociando', count: 0, value: money(0), cards: [] },
      { stage: 'closed', label: 'Fechado', count: 1, value: money(800_000), cards: [cardPayload('d-2', 'Cloud Sul', 'closed')] },
      { stage: 'paid', label: 'Pago', count: 0, value: money(0), cards: [] },
      { stage: 'lost', label: 'Perdido', count: 1, value: money(100_000), cards: [cardPayload('d-3', 'Banco X', 'lost', { lost_reason: 'Sem verba' })] },
    ],
  },
})

export const tiersPayload = () => ({
  data: [{ id: 't-ouro', name: 'Ouro', price: money(800_000), slots: 3, slots_taken: 1, slots_left: 2, benefits: ['Palco', 'Estande'], position: 0 }],
})
