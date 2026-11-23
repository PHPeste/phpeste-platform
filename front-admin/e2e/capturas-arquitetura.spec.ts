// Gera os prints do painel usados em docs/arquitetura (não é teste de
// comportamento; só roda com CAPTURAR_TELAS=1):
//   CAPTURAR_TELAS=1 npx playwright test e2e/capturas-arquitetura.spec.ts
// A API é simulada com page.route, como nos outros testes e2e.
import { fileURLToPath } from 'node:url'
import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page } from '@playwright/test'

const out = (name: string) => fileURLToPath(new URL(`../../docs/arquitetura/telas/${name}.png`, import.meta.url))
const session = { user: { id: 'u-me', name: 'Rita Organiza', email: 'rita@php-pb.net', avatarUrl: null }, token: 'e2e-token' }
const edition = (role: string) => ({
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHP-PB', slug: 'php-pb' },
  status: 'cfp_open', status_label: 'CFP aberto', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role,
})
const overview = {
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
  indicators: { tickets: { sold: 128, available: 400 }, revenue: { gross: 1920000, net: 1850000 }, proposals: { total: 47, reviewed: 31 }, speakers: { confirmed: 6, goal: 24 } },
  sections: {
    tickets_per_week: [12, 18, 25, 31, 22, 40, 55, 48],
    nordeste: { base: 'confirmed', people: 8, percent: 62, quota: 50, require_quota: true, missing_states: [{ uf: 'AL', name: 'Alagoas' }, { uf: 'SE', name: 'Sergipe' }] },
  },
  pending: [
    { key: 'proposals_without_reviewer', title: '7 propostas sem revisor', tone: 'warn', tag: 'Atenção', action_label: 'Atribuir', action_path: '/cfp/avaliacao?filtro=sem-revisor' },
    { key: 'speakers_no_response', title: '2 palestrantes sem resposta à confirmação', tone: 'warn', tag: 'Atenção', action_label: 'Ver palestrantes', action_path: '/palestrantes?confirmacao=sem-resposta' },
  ],
  milestones: [
    { key: 'cfp_closes', title: 'Fechamento do CFP', description: 'Propostas deixam de ser aceitas', date: '2027-04-15' },
    { key: 'cfp_results', title: 'Resultado para palestrantes', description: 'E-mails de aprovação e lista de espera', date: '2027-04-30' },
  ],
}
const criteria = [{ id: 'c-1', name: 'Relevância para a comunidade', description: 'O tema interessa?', weight: 3 }, { id: 'c-2', name: 'Clareza da proposta', description: null, weight: 2 }]
const proposal = (number: number, title: string) => ({
  id: `p-${number}`, number, title, track: { id: 't', name: 'Backend e arquitetura' }, summary: 'Como rodar Laravel Octane em produção sem surpresas: memória, estado entre requisições e deploy sem derrubar ninguém.', audience: 'Quem já usa Laravel em produção.',
  format: 'talk', format_label: 'Palestra de 30 min', duration_minutes: 30, level: 'intermediate', level_label: 'Intermediário', language: 'pt_BR', nordeste: true, speakers_hidden: true,
})

test.skip(!process.env.CAPTURAR_TELAS, 'Só gera os prints da documentação com CAPTURAR_TELAS=1')
test.use({ viewport: { width: 1440, height: 900 } })

async function signedIn(page: Page, role = 'organizador') {
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition(role)] } }))
}

test('login', async ({ page, goto }) => {
  await goto('/login', { waitUntil: 'hydration' })
  await page.screenshot({ path: out('painel-login') })
})

test('visão geral', async ({ page, goto }) => {
  await page.setViewportSize({ width: 1440, height: 1150 })
  await signedIn(page)
  await page.route('**/api/v1/editions/ed-27/**', (route) => route.request().url().endsWith('/overview') ? route.fulfill({ status: 200, json: { data: overview } }) : route.fulfill({ status: 404, json: {} }))
  await goto('/', { waitUntil: 'hydration' })
  await expect(page.getByRole('heading', { name: 'Visão geral', level: 1 })).toBeVisible()
  await page.screenshot({ path: out('painel-visao-geral') })
})

test('avaliação de propostas', async ({ page, goto }) => {
  await signedIn(page, 'avaliador')
  await page.route('**/api/v1/editions/ed-27/overview', (route) => route.abort())
  await page.route('**/api/v1/editions/ed-27/cfp/**', (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1/editions/ed-27/cfp', '')
    if (path === '/reviews/queue') return route.fulfill({ status: 200, json: { data: [{ ...proposal(1, 'Octane em produção'), my_review_status: 'pending' }, { ...proposal(2, 'Filas e idempotência'), my_review_status: 'pending' }] } })
    if (path.endsWith('/review')) return route.fulfill({ status: 200, json: { data: { proposal: proposal(1, 'Octane em produção'), criteria, review: null } } })
    return route.fulfill({ status: 404, json: {} })
  })
  await goto('/cfp/avaliacao', { waitUntil: 'hydration' })
  await expect(page.getByRole('heading', { name: 'Octane em produção', level: 2 })).toBeVisible()
  await page.screenshot({ path: out('painel-avaliacao-cfp') })
})

test('catálogo de componentes', async ({ page, goto }) => {
  await goto('/_dev/componentes', { waitUntil: 'hydration' })
  await page.screenshot({ path: out('painel-componentes') })
})

test('ingressos', async ({ page, goto }) => {
  await page.setViewportSize({ width: 1440, height: 1000 })
  await signedIn(page)
  const batch = (id: string, name: string, cents: number, sold: number, status: string, label: string) => ({ id, ticket_type_id: 'tt-1', name, position: 0, price: { amount: cents, currency: 'BRL' }, quantity: 150, allocated: sold, remaining: 150 - sold, starts_at: null, ends_at: '2027-06-30T23:59:00Z', on_sell_out: 'next', status, status_label: label })
  const types = [
    { id: 'tt-1', name: 'Inteira', description: 'Acesso aos dois dias', visibility: 'public', visibility_label: 'Qualquer pessoa', access_code: null, min_per_order: 1, max_per_order: 10, show_remaining: true, pass_fee_to_buyer: false, questions: [], position: 0, allocated: 188, quantity: 300, current_batch_id: 'b-2', batches: [batch('b-1', '1º lote', 12000, 150, 'sold_out', 'Esgotado'), batch('b-2', '2º lote', 15000, 38, 'on_sale', 'À venda')] },
    { id: 'tt-2', name: 'Estudante', description: null, visibility: 'code', visibility_label: 'Quem tem o código', access_code: 'UFPB2027', min_per_order: 1, max_per_order: 1, show_remaining: false, pass_fee_to_buyer: true, questions: [], position: 1, allocated: 41, quantity: 100, current_batch_id: 'b-3', batches: [{ ...batch('b-3', 'Lote único', 7500, 41, 'on_sale', 'À venda'), ticket_type_id: 'tt-2', quantity: 100, remaining: 59 }] },
  ]
  await page.route('**/api/v1/editions/ed-27/ticketing/**', (route) => {
    const path = new URL(route.request().url()).pathname
    if (path.endsWith('/ticket-types')) return route.fulfill({ status: 200, json: { data: types, meta: { gateway_ready: true, capacity: 400, allocated: 229, remaining: 171, reservation_minutes: 30, max_tickets_per_buyer: 4, gateway_fee_basis_points: 499 } } })
    return route.fulfill({ status: 200, json: { data: [] } })
  })
  await goto('/ingressos', { waitUntil: 'hydration' })
  await expect(page.getByRole('heading', { name: 'Ingressos', level: 1 })).toBeVisible()
  await page.screenshot({ path: out('painel-ingressos') })
})

test('pedidos', async ({ page, goto }) => {
  await page.setViewportSize({ width: 1440, height: 1000 })
  await signedIn(page)
  const money = (amount: number) => ({ amount, currency: 'BRL' })
  const item = (id: string, name: string, cancelled: string | null = null) => ({ id, ticket_type_id: 'tt-1', ticket_type_name: 'Inteira', batch_name: '2º lote', unit_price: money(15000), discount: money(0), fee: money(0), total: money(15000), attendee: { name, email: 'x@exemplo.com' }, cancelled_at: cancelled, external_code: null })
  const order = (number: number, name: string, email: string, status: string, label: string, total: number, extra: Record<string, unknown> = {}) => ({
    id: `0e1c6d1a-0000-4000-8000-0000000000${number}`, number, status, status_label: label, source: 'checkout', external: null, buyer: { name, email, document: '***.982.247-**' },
    subtotal: money(total), discount: money(0), fee: money(0), total: money(total), refunded: money(0), gateway_fee: money(Math.ceil(total * 0.0499)), requires_payment: true, coupon_code: null,
    expires_at: null, paid_at: '2027-06-10T12:00:00Z', created_at: `2027-06-${String(10 - (number % 7)).padStart(2, '0')}T14:${String(number % 60).padStart(2, '0')}:00Z`, items: [item('i-1', name)], ...extra,
  })
  const featured = order(58, 'Ana Souza', 'ana@exemplo.com', 'paid', 'Pago', 30000, { refunded: money(15000), items: [item('i-1', 'Ana Souza'), item('i-2', 'Bia Lima', '2027-06-11T10:00:00Z')] })
  const list = [
    order(61, 'Caio Reis', 'caio@exemplo.com', 'awaiting_payment', 'Aguardando pagamento', 15000),
    order(60, 'Duda Martins', 'duda@exemplo.com', 'paid', 'Pago', 15000),
    order(59, 'Edu Farias', 'edu@exemplo.com', 'expired', 'Expirado', 30000),
    featured,
    order(57, 'Fabi Nunes', 'fabi@exemplo.com', 'paid', 'Pago', 7500),
    order(56, 'Gil Andrade', 'gil@exemplo.com', 'cancelled', 'Cancelado', 15000),
  ]
  const history = [
    { id: 'h0', kind: 'created', title: 'Pedido criado', detail: null, actor: null, at: '2027-06-10T11:50:00Z' },
    { id: 'h1', kind: 'payment_started', title: 'Pagamento iniciado', detail: 'Pix', actor: null, at: '2027-06-10T11:51:00Z' },
    { id: 'h2', kind: 'paid', title: 'Pagamento confirmado', detail: null, actor: null, at: '2027-06-10T12:00:00Z' },
    { id: 'h3', kind: 'refunded', title: 'Reembolso de R$ 150,00', detail: 'Pedido de quem comprou · 1 ingresso(s) cancelado(s)', actor: { id: 'u-me', name: 'Rita Organiza' }, at: '2027-06-11T10:00:00Z' },
  ]
  const refund = { id: 'r-1', amount: money(15000), status: 'completed', status_label: 'Reembolsado', reason: 'buyer_request', reason_label: 'Pedido de quem comprou', note: 'Bia não vai poder ir', cancelled_item_ids: ['i-2'], failure_reason: null, created_at: '2027-06-11T10:00:00Z', completed_at: '2027-06-11T10:00:00Z' }
  await page.route('**/api/v1/editions/ed-27/ticketing/orders**', (route) => {
    const path = new URL(route.request().url()).pathname
    if (path.endsWith('/orders')) return route.fulfill({ status: 200, json: { data: list, meta: { current_page: 1, last_page: 9, total: 214, summary: { paid: 187, awaiting: 9, awaiting_expiring_today: 3, refunds: 4, refunded_cents: 52500, gross_cents: 2805000, gateway_fee_cents: 100980 } } } })
    if (path.endsWith('/history')) return route.fulfill({ status: 200, json: { data: history } })
    if (path.endsWith('/refunds')) return route.fulfill({ status: 200, json: { data: [refund] } })
    return route.fulfill({ status: 200, json: { data: featured, meta: { payment: { method: 'pix', method_label: 'Pix', status: 'paid', status_label: 'Pago', installments: 1, expires_at: null, paid_at: '2027-06-10T12:00:00Z', failure_reason: null } } } })
  })
  await goto('/pedidos', { waitUntil: 'hydration' })
  await page.getByRole('button', { name: 'Abrir pedido 58' }).click()
  await expect(page.getByTestId('order-history')).toContainText('Reembolso de R$ 150,00')
  // Espera a gaveta terminar de deslizar, senão o print sai com ela cortada.
  await page.waitForFunction(() => {
    const box = document.querySelector('dialog[open] .modal-box')?.getBoundingClientRect()
    return !!box && Math.abs(box.right - window.innerWidth) < 1
  })
  await page.screenshot({ path: out('painel-pedidos') })
})

test('pagamentos', async ({ page, goto }) => {
  await page.setViewportSize({ width: 1440, height: 1530 })
  await signedIn(page)
  const active = {
    id: 'a-2', driver: 'asaas', environment: 'production', environment_label: 'Produção', credentials_hint: '••••x9Qa',
    holder: { type: 'company', type_label: 'Pessoa jurídica (CNPJ)', name: 'Associação PHP Paraíba', document: '11.222.***/0001-**' },
    methods: [{ key: 'pix', label: 'Pix' }, { key: 'card', label: 'Cartão de crédito' }], max_installments: 3, pix_expiration_minutes: 30,
    active: true, ready: true, verified_at: '2027-05-20T13:10:00Z', webhook_verified_at: '2027-05-20T13:14:00Z', webhook_rejections: { count: 0, last_at: null },
    retired_at: null, created_at: '2027-05-20T13:00:00Z', webhook_url: 'https://api.phpeste.dev/api/v1/webhooks/payments/Hk2…', last_event_at: '2027-06-10T12:00:00Z',
  }
  const previous = { ...active, id: 'a-1', environment: 'sandbox', environment_label: 'Sandbox (testes)', credentials_hint: '••••abcd', holder: { ...active.holder, type: 'individual', name: 'Fulana de Tal' }, active: false, ready: false, retired_at: '2027-05-20T13:00:00Z', webhook_url: null, last_event_at: null }
  const event = (id: string, type: string, status: string, label: string, minutes: number, extra: Record<string, unknown> = {}) => ({
    id, payment_account_id: 'a-2', driver: 'asaas', event_id: `evt_${id}`, type, gateway_charge_id: `pay_${id}`, status, status_label: label, result: null, attempts: 1, can_reprocess: false, next_attempt_at: null, processed_at: null,
    received_at: new Date(Date.UTC(2027, 5, 10, 12, 0) - minutes * 60_000).toISOString(), ...extra,
  })
  const events = [
    event('481', 'PAYMENT_RECEIVED', 'processed', 'Processado', 0, { result: 'Pedido #58 pago' }),
    event('480', 'PAYMENT_CREATED', 'ignored', 'Ignorado', 9, { result: 'Evento sem efeito no pedido', can_reprocess: true }),
    event('479', 'PAYMENT_RECEIVED', 'failed', 'Com falha', 42, { result: 'Cobrança não encontrada', attempts: 5, can_reprocess: true }),
    event('478', 'PAYMENT_REFUNDED', 'processed', 'Processado', 95, { result: 'Reembolso do pedido #41 confirmado' }),
  ]
  await page.route('**/api/v1/editions/ed-27/payments/**', (route) => {
    const path = new URL(route.request().url()).pathname
    if (path.endsWith('/webhook-events')) return route.fulfill({ status: 200, json: { data: events, meta: { current_page: 1, last_page: 1, total: 4 } } })
    return route.fulfill({ status: 200, json: { data: { active, previous: [previous], drivers: [{ key: 'asaas', label: 'Asaas', needs_public_key: false }, { key: 'mercadopago', label: 'Mercado Pago', needs_public_key: true }] } } })
  })
  await goto('/pagamentos', { waitUntil: 'hydration' })
  await expect(page.getByTestId('webhook-events')).toContainText('PAYMENT_REFUNDED')
  await page.screenshot({ path: out('painel-pagamentos') })
})

test('participantes', async ({ page, goto }) => {
  await page.setViewportSize({ width: 1440, height: 1000 })
  await signedIn(page)
  const people: Array<[string, string, string | null, string | null, string | null, string | null, string | null]> = [
    ['Marina Alves', 'Geral', 'Marina', 'Empresa A', 'M', null, null],
    ['Diego Costa', 'Estudante', 'Diego', 'Universidade B', 'G', 'Intérprete de Libras', null],
    ['Luana Pires', 'Apoiador', 'Luana', 'PHP-PB', 'BL-P', null, null],
    ['Bruno Teles', 'Geral', null, null, null, 'Acesso para cadeira de rodas', 'Camila Rocha'],
    ['Aline Duarte', 'Estudante', 'Aline', 'Universidade B', 'BL-M', null, null],
    ['Rafael Nunes', 'Geral', 'Rafa', null, null, null, null],
    ['Tânia Melo', 'Geral', 'Tânia', 'Comunidade PHP-CE', 'P', null, null],
  ]
  const data = people.map(([name, type, badge, company, size, accessibility, from], index) => ({
    id: `t-${index}`, code: `K7QM-${1000 + index}`, status: 'active', status_label: 'Válido', order_id: 'o-1', order_number: 40 + index, ticket_type_id: 'tt-1', ticket_type_name: type, batch_name: '1º lote',
    holder: { name, email: `${name.split(' ')[0]!.toLowerCase().normalize('NFD').replace(/[^a-z]/g, '')}@exemplo.dev`, badge_name: badge, company, tshirt_size: size, accessibility },
    missing: [...(badge ? [] : ['badge_name']), ...(size ? [] : ['tshirt_size'])], transferred_from: from, issued_at: null, cancelled_at: null, last_sent_at: null,
  }))
  await page.route('**/api/v1/editions/ed-27/ticketing/tickets**', (route) => route.fulfill({
    status: 200, json: { data, meta: { current_page: 1, last_page: 3, total: 128, summary: { participants: 128, complete: 109, missing: 19, accessibility: 4, transferred: 3 } } },
  }))
  await goto('/participantes', { waitUntil: 'hydration' })
  await expect(page.getByRole('row', { name: /Bruno Teles/ })).toContainText('Transferido de Camila Rocha')
  await page.screenshot({ path: out('painel-participantes') })
})
