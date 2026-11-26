export const summaryPayload = () => ({
  data: {
    revenue: {
      tickets: {
        gross: 1_920_000, refunded: 30_000, fees: 70_000, net: 1_820_000,
        by_account: [{ account: { id: 'acc-1', driver: 'asaas', holder_type: 'cnpj', holder_name: 'Associação PHP-PB', holder_document: '**.***.***/0001-90', retired: false }, orders: 120, gross: 1_920_000, refunded: 30_000, fees: 70_000, net: 1_820_000, estimated_fees: 0 }],
      },
      sponsors: { received: 150_000, outstanding: 900_000 },
      total: 1_970_000,
    },
    expenses: { paid: 1_140_000, pending: 4_120_000, overdue: 1 },
    balance: 830_000,
    forecast: -2_390_000,
    budget: {
      categories: [
        { id: 'cat-local', name: 'Local', budget: 1_800_000, committed: 900_000, paid: 900_000, remaining: 900_000, percent_used: 50, over_budget: false },
        { id: 'cat-brindes', name: 'Brindes', budget: 200_000, committed: 240_000, paid: 240_000, remaining: -40_000, percent_used: 120, over_budget: true },
        { id: 'cat-outros', name: 'Outros', budget: null, committed: 0, paid: 0, remaining: null, percent_used: null, over_budget: false },
      ],
      budgeted: 2_000_000, committed: 1_140_000, paid: 1_140_000,
    },
  },
})

export const expensePayload = (id: string, description: string, status: string, extra: Record<string, unknown> = {}) => ({
  id, description, category: { id: 'cat-local', name: 'Local' }, supplier: { id: 'l-1', name: 'Espaço Mar Azul' }, amount: 900_000,
  due_on: '2027-07-10', paid_on: status === 'paid' ? '2027-07-09' : null, status, status_label: status === 'paid' ? 'Paga' : 'A pagar', overdue: false, notes: null, receipt: null, ...extra,
})
