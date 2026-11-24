import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Rita Organiza', email: 'rita@php-pb.net', avatarUrl: null }, token: 'e2e-token' }
const edition = {
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHP-PB', slug: 'php-pb' },
  status: 'sales_open', status_label: 'Vendas abertas', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role: 'organizador',
}
const CLOUD = '0e1c6d1a-0000-4000-8000-0000000000c1'
const money = (amount: number) => ({ amount, currency: 'BRL' })
const card = (id: string, sponsorId: string, name: string, stage: string, extra: Record<string, unknown> = {}) => ({
  id, sponsor: { id: sponsorId, name }, tier: { id: 't-ouro', name: 'Ouro' }, stage, stage_label: stage, value: money(800_000), received: money(0), outstanding: money(800_000),
  owner: null, lost_reason: null, position: 0, closed_at: null, paid_at: null, lost_at: null, created_at: '2027-03-01T12:00:00Z', last_contact: null, ...extra,
})
const board = (closed: boolean) => ({
  data: {
    indicators: { goal: money(6_000_000), committed: money(closed ? 1_600_000 : 800_000), received: money(150_000), pipeline: money(closed ? 0 : 800_000), deliverables_overdue: 0 },
    columns: [
      { stage: 'prospecting', label: 'Prospecção', count: 1, value: money(800_000), cards: closed ? [] : [card('d-1', 's-1', 'Hospedagem Norte', 'prospecting', { last_contact: { channel: 'email', channel_label: 'E-mail', summary: 'Contato feito', happened_at: '2027-03-05T12:00:00Z' } })] },
      { stage: 'negotiating', label: 'Negociando', count: 0, value: money(0), cards: [] },
      { stage: 'closed', label: 'Fechado', count: 1, value: money(800_000), cards: [card('d-2', CLOUD, 'Cloud Sul', 'closed'), ...(closed ? [card('d-1', 's-1', 'Hospedagem Norte', 'closed')] : [])] },
      { stage: 'paid', label: 'Pago', count: 0, value: money(0), cards: [] },
      { stage: 'lost', label: 'Perdido', count: 0, value: money(0), cards: [] },
    ],
  },
})
const tiers = { data: [{ id: 't-ouro', name: 'Ouro', price: money(800_000), slots: 2, slots_taken: 2, slots_left: 0, benefits: ['Palco', 'Estande'], position: 0 }] }
const cloud = (received: number) => ({
  data: {
    id: CLOUD, name: 'Cloud Sul', website: null, contact: { name: 'Joana', email: 'joana@cloud.com', phone: null }, notes: null, created_at: '2027-03-01T12:00:00Z',
    sponsorships: [{
      ...card('d-2', CLOUD, 'Cloud Sul', 'closed'), sponsor: { id: CLOUD }, stage_label: 'Fechado', received: money(received), outstanding: money(800_000 - received),
      payments: received ? [{ id: 'p-1', amount: money(received), paid_on: '2027-03-10', note: null, recorded_by: { id: 'u-me', name: 'Rita Organiza' } }] : [],
      deliverables: [{ id: 'e-1', sponsorship_id: 'd-2', title: 'Logo no site', owner: { id: 'u-me', name: 'Rita Organiza' }, due_on: '2027-06-01', status: 'pending', status_label: 'A fazer', overdue: false, done_at: null, position: 0 }],
    }],
    contacts: [{ id: 'c-1', channel: 'meeting', channel_label: 'Reunião', happened_at: '2027-03-05T12:00:00Z', summary: 'Contrato assinado', author: { id: 'u-me', name: 'Rita Organiza' } }],
    attachments: [{ id: 'a-1', kind: 'contract', kind_label: 'Contrato', name: 'contrato-cloud-sul.pdf', mime_type: 'application/pdf', size_bytes: 20480, uploaded_by: null, created_at: '2027-03-05T12:00:00Z' }],
  },
})

interface Call { method: string, path: string, body: unknown }

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, url: string, handle: (route: Route, call: Call) => Promise<boolean> | boolean = () => false) {
  const calls: Call[] = []
  const state = { closed: false, received: 0 }
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition] } }))
  await page.route('**/api/v1/editions/ed-27/team', (route) => route.fulfill({ status: 200, json: { data: { members: [{ id: 'm-1', user_id: 'u-me', name: 'Rita Organiza', email: 'rita@php-pb.net' }], invitations: [], summary: {} } } }))
  await page.route('**/api/v1/editions/ed-27/sponsors/**', async (route) => {
    const request = route.request()
    const call = { method: request.method(), path: new URL(request.url()).pathname.replace('/api/v1/editions/ed-27/sponsors', ''), body: request.postDataJSON?.() ?? null }
    calls.push(call)
    if (await handle(route, call)) return
    if (call.path === '/board') return route.fulfill({ status: 200, json: board(state.closed) })
    if (call.path === '/tiers') return route.fulfill({ status: 200, json: tiers })
    if (call.path.endsWith('/move')) {
      state.closed = true
      return route.fulfill({ status: 200, json: { data: card('d-1', 's-1', 'Hospedagem Norte', 'closed') } })
    }
    if (call.path === `/companies/${CLOUD}`) return route.fulfill({ status: 200, json: cloud(state.received) })
    if (call.path === '/sponsorships/d-2/payments') {
      state.received = 400_000
      return route.fulfill({ status: 201, json: { data: {} } })
    }
    if (call.path === '/attachments/a-1/url') return route.fulfill({ status: 200, json: { data: { url: 'http://localhost:9/arquivo-assinado', expires_at: '2027-03-10T12:10:00Z' } } })
    return route.fulfill({ status: 200, json: { data: {} } })
  })
  await goto(url, { waitUntil: 'hydration' })
  return calls
}

test.describe('Patrocinadores', () => {
  test('mostra os indicadores, o funil e as cotas', async ({ page, goto }) => {
    await open(page, goto, '/patrocinadores')
    await expect(page.getByRole('heading', { name: 'Patrocinadores', level: 1 })).toBeVisible()

    const indicators = page.getByTestId('sponsors-indicators')
    await expect(indicators).toContainText('R$ 60.000')
    await expect(indicators).toContainText('13% da meta')
    await expect(indicators).toContainText('1 empresa')
    await expect(page.getByTestId('sponsor-card').filter({ hasText: 'Hospedagem Norte' })).toContainText('Contato feito')
    await expect(page.getByRole('row', { name: /Ouro/ })).toContainText('Palco, Estande')
  })

  test('mover para uma cota sem vaga pede confirmação e manda confirm', async ({ page, goto }) => {
    let first = true
    const calls = await open(page, goto, '/patrocinadores', (route, call) => {
      if (call.path === '/sponsorships/d-1/move' && first) {
        first = false
        route.fulfill({ status: 422, json: { code: 'validation_failed', message: 'A cota Ouro tem 2 vagas e 2 já estão fechadas. Confirme para fechar mais um acordo nela.', errors: { confirm: ['A cota Ouro tem 2 vagas e 2 já estão fechadas. Confirme para fechar mais um acordo nela.'] }, request_id: 'r' } })
        return true
      }
      return false
    })

    await page.getByRole('combobox', { name: 'Mover Hospedagem Norte para outra etapa' }).selectOption('closed')
    const dialog = page.getByRole('dialog', { name: 'Cota sem vaga' })
    await expect(dialog).toContainText('A cota Ouro tem 2 vagas')
    await expect(page.locator('[data-stage="prospecting"]')).toContainText('Hospedagem Norte')

    await dialog.getByRole('button', { name: 'Fechar mesmo assim' }).click()
    await expect(page.locator('[data-stage="closed"]')).toContainText('Hospedagem Norte')
    await expect(page.getByText('Hospedagem Norte foi para Fechado.')).toBeVisible()
    expect(calls.filter((call) => call.path === '/sponsorships/d-1/move').at(-1)?.body).toMatchObject({ stage: 'closed', confirm: true })
  })

  test('erro ao mover devolve o card para a coluna de antes', async ({ page, goto }) => {
    await open(page, goto, '/patrocinadores', (route, call) => {
      if (!call.path.endsWith('/move')) return false
      route.fulfill({ status: 422, json: { code: 'validation_failed', message: 'x', errors: { stage: ['Faltam R$ 8.000,00 para este acordo estar pago. Registre o pagamento primeiro.'] }, request_id: 'r' } })
      return true
    })

    await page.getByRole('combobox', { name: 'Mover Cloud Sul para outra etapa' }).selectOption('paid')
    await expect(page.getByText('Faltam R$ 8.000,00 para este acordo estar pago.')).toBeVisible()
    await expect(page.locator('[data-stage="closed"]')).toContainText('Cloud Sul')
    await expect(page.locator('[data-stage="paid"]')).not.toContainText('Cloud Sul')
  })

  test('no detalhe registra pagamento, mostra entregas e histórico e baixa o anexo por URL assinada', async ({ page, goto }) => {
    const calls = await open(page, goto, `/patrocinadores/${CLOUD}`)
    await expect(page.getByRole('heading', { name: 'Cloud Sul', level: 1 })).toBeVisible()
    await expect(page.getByTestId('sponsor-stage')).toHaveText('Fechado')
    await expect(page.getByRole('row', { name: /Logo no site/ })).toContainText('Rita Organiza')
    await expect(page.getByTestId('sponsor-history')).toContainText('Contrato assinado')

    await page.getByRole('button', { name: 'Registrar pagamento' }).click()
    const dialog = page.getByRole('dialog', { name: 'Registrar pagamento' })
    await dialog.getByLabel('Valor (R$)').fill('4.000')
    await dialog.getByRole('button', { name: 'Registrar pagamento' }).click()
    await expect(page.getByTestId('sponsor-received')).toHaveText('R$ 4.000')
    expect(calls.find((call) => call.path === '/sponsorships/d-2/payments')?.body).toMatchObject({ amount_cents: 400_000 })

    const navigation = page.waitForRequest('http://localhost:9/arquivo-assinado')
    await page.getByRole('button', { name: 'Baixar contrato-cloud-sul.pdf' }).click()
    await navigation
    expect(calls.some((call) => call.path === '/attachments/a-1/url')).toBe(true)
  })
})
