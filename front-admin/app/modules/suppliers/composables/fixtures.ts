const money = (amount: number) => ({ amount, currency: 'BRL' })

export const linkPayload = (id: string, name: string, status: string, extra: Record<string, unknown> = {}) => ({
  id,
  supplier: {
    id: `s-${id}`, name, service: 'catering', service_label: 'Alimentação', description: null, document: null, website: null, city: 'João Pessoa', state: 'PB',
    contacts_visible: true, contacts: [{ id: `c-${id}`, name: 'Joana', role: null, email: 'joana@x.dev', phone: '(83) 90000-0001' }],
  },
  status, status_label: status, owner: null, quoted: money(960_000), contracted: null, accepted_quote_id: null, decline_reason: null, hired_at: null, declined_at: null, quotes_count: 1,
  ...extra,
})

export const listPayload = () => ({
  data: [
    linkPayload('l-1', 'Espaço Mar Azul', 'hired', { quoted: money(1_800_000), contracted: money(1_800_000) }),
    linkPayload('l-2', 'Buffet Sabor da Terra', 'quoting'),
    linkPayload('l-3', 'Gráfica Rápida', 'declined', { quoted: money(230_000), supplier: { ...linkPayload('x', 'Gráfica Rápida', 'declined').supplier, contacts_visible: false, contacts: null } }),
  ],
  totals: { count: 3, quoted: money(2_760_000), contracted: money(1_800_000), by_status: { to_contact: 0, quoting: 1, hired: 1, declined: 1 } },
})
