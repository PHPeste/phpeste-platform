import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Rita Organiza', email: 'rita@php-pb.net', avatarUrl: null }, token: 'e2e-token' }
const edition = {
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHP-PB', slug: 'php-pb' },
  status: 'sales_open', status_label: 'Vendas abertas', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role: 'organizador',
}
const BUFFET = '0e1c6d1a-0000-4000-8000-0000000000b1'
const money = (amount: number) => ({ amount, currency: 'BRL' })
const link = (id: string, name: string, status: string, extra: Record<string, unknown> = {}) => ({
  id,
  supplier: {
    id: `s-${id}`, name, service: 'catering', service_label: 'Alimentação', description: null, document: null, website: null, city: null, state: null,
    contacts_visible: true, contacts: [{ id: `c-${id}`, name: 'Joana', role: null, email: null, phone: '(83) 90000-0002' }],
  },
  status, status_label: { hired: 'Contratado', quoting: 'Em orçamento', to_contact: 'A contatar', declined: 'Recusado' }[status], owner: null,
  quoted: money(960_000), contracted: null, accepted_quote_id: null, decline_reason: null, hired_at: null, declined_at: null, quotes_count: 1, ...extra,
})
const all = (hired: boolean) => [
  link('l-1', 'Espaço Mar Azul', 'hired', { quoted: money(1_800_000), contracted: money(1_800_000) }),
  link(BUFFET, 'Buffet Sabor da Terra', hired ? 'hired' : 'quoting', hired ? { contracted: money(960_000), accepted_quote_id: 'q-1' } : {}),
  link('l-3', 'Gráfica Rápida', 'declined', { quoted: money(230_000), supplier: { ...link('x', 'Gráfica Rápida', 'declined').supplier, contacts_visible: false, contacts: null } }),
]
const totals = (rows: ReturnType<typeof all>) => ({
  count: rows.length,
  quoted: money(rows.filter((row) => row.status !== 'declined').reduce((sum, row) => sum + (row.quoted as { amount: number }).amount, 0)),
  contracted: money(rows.filter((row) => row.status === 'hired').reduce((sum, row) => sum + ((row.contracted as { amount: number } | null)?.amount ?? 0), 0)),
  by_status: { to_contact: 0, quoting: rows.filter((row) => row.status === 'quoting').length, hired: rows.filter((row) => row.status === 'hired').length, declined: 1 },
})
const buffet = (hired: boolean) => ({
  ...all(hired)[1],
  quotes: [
    { id: 'q-1', amount: money(960_000), description: 'Coffee break + almoço, 2 dias', received_on: '2027-03-01', valid_until: '2027-07-30', expired: false, status: hired ? 'accepted' : 'received', status_label: hired ? 'Aceito' : 'Recebido', recorded_by: null },
    { id: 'q-2', amount: money(420_000), description: 'Só coffee break, 2 dias', received_on: '2027-03-01', valid_until: '2027-07-30', expired: false, status: 'received', status_label: 'Recebido', recorded_by: null },
  ],
  attachments: [],
  activities: [{ id: 'a-1', kind: 'note', channel: 'phone', channel_label: 'Telefone', summary: 'Primeiro contato por telefone', author: { id: 'u-me', name: 'Rita Organiza' }, happened_at: '2027-03-01T12:00:00Z' }],
})

interface Call { method: string, path: string, query: string, body: unknown }

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, url: string, handle: (route: Route, call: Call) => Promise<boolean> | boolean = () => false) {
  const calls: Call[] = []
  const state = { hired: false }
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition] } }))
  await page.route('**/api/v1/editions/ed-27/team', (route) => route.fulfill({ status: 200, json: { data: { members: [], invitations: [], summary: {} } } }))
  await page.route('**/api/v1/editions/ed-27/suppliers**', async (route) => {
    const request = route.request()
    const url = new URL(request.url())
    const call = { method: request.method(), path: url.pathname.replace('/api/v1/editions/ed-27/suppliers', ''), query: url.search, body: request.postDataJSON?.() ?? null }
    calls.push(call)
    if (await handle(route, call)) return
    if (call.path === '') {
      const status = url.searchParams.get('status')
      const rows = all(state.hired).filter((row) => !status || row.status === status)
      return route.fulfill({ status: 200, json: { data: rows, totals: totals(rows) } })
    }
    if (call.path === '/directory') {
      return route.fulfill({ status: 200, json: { data: [{ ...link('x', 'Som e Luz JP', 'hired').supplier, id: 's-som', service_label: 'Som, luz e vídeo', in_this_edition: false, editions: [{ id: 'ed-26', name: 'PHPeste 2026', status: 'hired', status_label: 'Contratado' }] }] } })
    }
    if (call.path === '/directory/s-som') return route.fulfill({ status: 201, json: { data: { ...link('l-som', 'Som e Luz JP', 'to_contact'), quotes: [], attachments: [], activities: [] } } })
    if (call.path === `/${BUFFET}/status`) {
      state.hired = true
      return route.fulfill({ status: 200, json: { data: buffet(true) } })
    }
    if (call.path === `/${BUFFET}`) return route.fulfill({ status: 200, json: { data: buffet(state.hired) } })
    return route.fulfill({ status: 200, json: { data: { ...link('l-som', 'Som e Luz JP', 'to_contact'), quotes: [], attachments: [], activities: [] } } })
  })
  await goto(url, { waitUntil: 'hydration' })
  return calls
}

test.describe('Fornecedores', () => {
  test('os totais do topo batem com a soma da lista, também com filtro', async ({ page, goto }) => {
    const calls = await open(page, goto, '/fornecedores')
    await expect(page.getByRole('heading', { name: 'Fornecedores', level: 1 })).toBeVisible()

    const top = page.getByTestId('suppliers-totals')
    const footer = page.getByTestId('suppliers-list-total')
    await expect(top).toContainText('R$ 27.600')
    await expect(top).toContainText('R$ 18.000')
    await expect(footer).toContainText('R$ 27.600')
    await expect(footer).toContainText('R$ 18.000')
    await expect(page.getByRole('row', { name: /Gráfica Rápida/ })).toContainText('Contato restrito')

    await page.getByRole('group', { name: 'Filtrar por status' }).getByRole('button', { name: 'Em orçamento' }).click()
    await expect(page.getByRole('row', { name: /Espaço Mar Azul/ })).toHaveCount(0)
    await expect(top).toContainText('R$ 9.600')
    await expect(footer).toContainText('R$ 9.600')
    expect(calls.some((call) => call.path === '' && call.query.includes('status=quoting'))).toBe(true)
  })

  test('traz fornecedor de outra edição da comunidade', async ({ page, goto }) => {
    const calls = await open(page, goto, '/fornecedores')
    await page.getByRole('button', { name: 'Trazer de outra edição' }).click()

    const dialog = page.getByRole('dialog', { name: 'Trazer de outra edição' })
    await expect(dialog).toContainText('PHPeste 2026 (Contratado)')
    await dialog.getByRole('button', { name: 'Trazer Som e Luz JP para esta edição' }).click()
    await expect(page).toHaveURL(/\/fornecedores\/l-som$/)
    expect(calls.some((call) => call.method === 'POST' && call.path === '/directory/s-som')).toBe(true)
  })

  test('contrata pelo orçamento escolhido no detalhe', async ({ page, goto }) => {
    const calls = await open(page, goto, `/fornecedores/${BUFFET}`)
    await expect(page.getByRole('heading', { name: 'Buffet Sabor da Terra', level: 1 })).toBeVisible()
    await expect(page.getByRole('row', { name: /Só coffee break/ })).toContainText('R$ 4.200')
    await expect(page.getByTestId('supplier-activities')).toContainText('Primeiro contato por telefone')

    await page.getByRole('button', { name: 'Contratar', exact: true }).click()
    const dialog = page.getByRole('dialog', { name: 'Contratar Buffet Sabor da Terra' })
    await dialog.getByLabel('Orçamento aceito').selectOption('q-1')
    await dialog.getByRole('button', { name: 'Contratar' }).click()

    await expect(page.getByTestId('supplier-status')).toHaveText('Contratado')
    await expect(page.getByTestId('supplier-contracted')).toContainText('R$ 9.600')
    expect(calls.find((call) => call.path === `/${BUFFET}/status`)?.body).toEqual({ status: 'hired', quote_id: 'q-1' })
  })

  test('recusar sem motivo não chama a API', async ({ page, goto }) => {
    const calls = await open(page, goto, `/fornecedores/${BUFFET}`)
    await page.getByRole('button', { name: 'Recusar', exact: true }).first().click()
    const dialog = page.getByRole('dialog', { name: 'Recusar Buffet Sabor da Terra' })
    await dialog.getByRole('button', { name: 'Recusar' }).click()
    await expect(dialog).toContainText('Preencha este campo.')
    expect(calls.some((call) => call.path.endsWith('/status'))).toBe(false)
  })
})
