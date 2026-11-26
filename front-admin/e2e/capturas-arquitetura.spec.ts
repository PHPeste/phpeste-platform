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

test('patrocinadores', async ({ page, goto }) => {
  await page.setViewportSize({ width: 1440, height: 1000 })
  await signedIn(page)
  const money = (amount: number) => ({ amount, currency: 'BRL' })
  const card = (id: string, name: string, stage: string, tier: string, value: number, extra: Record<string, unknown> = {}) => ({
    id, sponsor: { id: `s-${id}`, name }, tier: { id: `t-${tier}`, name: tier }, stage, stage_label: stage, value: money(value), received: money(0), outstanding: money(value),
    owner: null, lost_reason: null, position: 0, closed_at: null, paid_at: null, lost_at: null, created_at: '2027-03-01T12:00:00Z', last_contact: null, ...extra,
  })
  const contact = (summary: string) => ({ last_contact: { channel: 'email', channel_label: 'E-mail', summary, happened_at: '2027-03-05T12:00:00Z' } })
  const columns = [
    { stage: 'prospecting', label: 'Prospecção', count: 2, value: money(1_200_000), cards: [card('1', 'Hospedagem Norte', 'prospecting', 'Ouro', 800_000, contact('Contato feito')), card('2', 'Banco Digital X', 'prospecting', 'Prata', 400_000, contact('Aguardando resposta'))] },
    { stage: 'negotiating', label: 'Negociando', count: 1, value: money(400_000), cards: [card('3', 'Loja de Cursos Y', 'negotiating', 'Prata', 400_000, contact('Proposta enviada'))] },
    { stage: 'closed', label: 'Fechado', count: 2, value: money(900_000), cards: [card('4', 'Cloud Sul', 'closed', 'Ouro', 800_000, contact('Contrato assinado')), card('5', 'Revista Dev Z', 'closed', 'Apoio', 100_000, contact('Contrato assinado'))] },
    { stage: 'paid', label: 'Pago', count: 1, value: money(150_000), cards: [card('6', 'Café do Sertão', 'paid', 'Apoio', 150_000, { paid_at: '2027-10-12T12:00:00Z' })] },
    { stage: 'lost', label: 'Perdido', count: 0, value: money(0), cards: [] },
  ]
  await page.route('**/api/v1/editions/ed-27/team', (route) => route.fulfill({ status: 200, json: { data: { members: [], invitations: [], summary: {} } } }))
  await page.route('**/api/v1/editions/ed-27/sponsors/board', (route) => route.fulfill({
    status: 200, json: { data: { indicators: { goal: money(6_000_000), committed: money(1_050_000), received: money(150_000), pipeline: money(1_600_000), deliverables_overdue: 0 }, columns } },
  }))
  await page.route('**/api/v1/editions/ed-27/sponsors/tiers', (route) => route.fulfill({
    status: 200, json: { data: [
      { id: 't-Ouro', name: 'Ouro', price: money(800_000), slots: 3, slots_taken: 1, slots_left: 2, benefits: ['Palco', 'estande', '6 ingressos'], position: 0 },
      { id: 't-Prata', name: 'Prata', price: money(400_000), slots: 5, slots_taken: 0, slots_left: 5, benefits: ['Logo no site', '3 ingressos'], position: 1 },
      { id: 't-Apoio', name: 'Apoio', price: money(150_000), slots: 10, slots_taken: 2, slots_left: 8, benefits: ['Logo no site', '1 ingresso'], position: 2 },
    ] },
  }))
  await goto('/patrocinadores', { waitUntil: 'hydration' })
  await expect(page.getByTestId('sponsor-card').filter({ hasText: 'Café do Sertão' })).toBeVisible()
  await page.screenshot({ path: out('painel-patrocinadores') })
})

test('fornecedores', async ({ page, goto }) => {
  await page.setViewportSize({ width: 1440, height: 1000 })
  await signedIn(page)
  const money = (amount: number) => ({ amount, currency: 'BRL' })
  const rows: Array<[string, string, string, string, number, number | null, boolean]> = [
    ['Espaço Mar Azul', 'venue', 'Local', 'hired', 1_800_000, 1_800_000, true],
    ['Buffet Sabor da Terra', 'catering', 'Alimentação', 'quoting', 960_000, null, true],
    ['Som e Luz JP', 'audio_visual', 'Som, luz e vídeo', 'quoting', 650_000, null, true],
    ['Gráfica Rápida', 'printing', 'Gráfica', 'to_contact', 230_000, null, false],
    ['Camisetas Nordeste', 'merch', 'Brindes e camisetas', 'hired', 480_000, 480_000, true],
  ]
  const labels: Record<string, string> = { hired: 'Contratado', quoting: 'Em orçamento', to_contact: 'A contatar', declined: 'Recusado' }
  const data = rows.map(([name, service, serviceLabel, status, quoted, contracted, visible], index) => ({
    id: `l-${index}`,
    supplier: {
      id: `s-${index}`, name, service, service_label: serviceLabel, description: null, document: null, website: null, city: 'João Pessoa', state: 'PB',
      contacts_visible: visible, contacts: visible ? [{ id: `c-${index}`, name: 'Contato', role: null, email: null, phone: `(83) 90000-000${index + 1}` }] : null,
    },
    status, status_label: labels[status], owner: null, quoted: money(quoted), contracted: contracted === null ? null : money(contracted),
    accepted_quote_id: null, decline_reason: null, hired_at: null, declined_at: null, quotes_count: 1,
  }))
  await page.route('**/api/v1/editions/ed-27/team', (route) => route.fulfill({ status: 200, json: { data: { members: [], invitations: [], summary: {} } } }))
  await page.route('**/api/v1/editions/ed-27/suppliers', (route) => route.fulfill({
    status: 200, json: { data, totals: { count: 5, quoted: money(4_120_000), contracted: money(2_280_000), by_status: { to_contact: 1, quoting: 2, hired: 2, declined: 0 } } },
  }))
  await goto('/fornecedores', { waitUntil: 'hydration' })
  await expect(page.getByTestId('suppliers-list-total')).toContainText('R$ 41.200')
  await page.screenshot({ path: out('painel-fornecedores') })
})

test('finanças', async ({ page, goto }) => {
  await page.setViewportSize({ width: 1440, height: 1000 })
  await signedIn(page)
  const categories: Array<[string, number | null, number]> = [
    ['Local', 1_800_000, 900_000], ['Alimentação', 960_000, 0], ['Som, luz e vídeo', 650_000, 0], ['Gráfica e brindes', 480_000, 240_000], ['Viagem de palestrantes', 720_000, 0],
  ]
  const summary = {
    revenue: {
      tickets: {
        gross: 1_990_000, refunded: 0, fees: 70_000, net: 1_920_000,
        by_account: [{ account: { id: 'acc-1', driver: 'asaas', holder_type: 'cnpj', holder_name: 'Associação PHP-PB', holder_document: '**.***.***/0001-90', retired: false }, orders: 128, gross: 1_990_000, refunded: 0, fees: 70_000, net: 1_920_000, estimated_fees: 0 }],
      },
      sponsors: { received: 150_000, outstanding: 900_000 },
      total: 2_070_000,
    },
    expenses: { paid: 1_140_000, pending: 1_850_000, overdue: 0 },
    balance: 930_000,
    forecast: -20_000,
    budget: {
      categories: categories.map(([name, budget, committed], index) => ({
        id: `cat-${index}`, name, budget, committed, paid: committed, remaining: budget === null ? null : budget - committed,
        percent_used: budget ? Math.floor(committed * 100 / budget) : null, over_budget: false,
      })),
      budgeted: 4_610_000, committed: 1_140_000, paid: 1_140_000,
    },
  }
  const expenses: Array<[string, string, string | null, number, string, string]> = [
    ['Sinal do espaço', 'Local', 'Espaço Mar Azul', 900_000, '2027-07-10', 'paid'],
    ['Camisetas, 50%', 'Gráfica e brindes', 'Camisetas Nordeste', 240_000, '2027-07-20', 'paid'],
    ['Buffet, sinal', 'Alimentação', 'Buffet Sabor da Terra', 480_000, '2027-08-15', 'pending'],
    ['Som e luz', 'Som, luz e vídeo', 'Som e Luz JP', 650_000, '2027-10-01', 'pending'],
    ['Passagens de palestrantes', 'Viagem de palestrantes', null, 720_000, '2027-09-15', 'pending'],
  ]
  const data = expenses.map(([description, category, supplier, amount, due, status], index) => ({
    id: `e-${index}`, description, category: { id: `c-${index}`, name: category }, supplier: supplier ? { id: `s-${index}`, name: supplier } : null, amount, due_on: due,
    paid_on: status === 'paid' ? due : null, status, status_label: status === 'paid' ? 'Paga' : 'A pagar', overdue: false, notes: null,
    receipt: status === 'paid' ? { name: 'comprovante.pdf', mime_type: 'application/pdf', size_bytes: 2048 } : null,
  }))
  await page.route('**/api/v1/editions/ed-27/finance/summary', (route) => route.fulfill({ status: 200, json: { data: summary } }))
  await page.route('**/api/v1/editions/ed-27/finance/categories', (route) => route.fulfill({ status: 200, json: { data: [] } }))
  await page.route('**/api/v1/editions/ed-27/finance/suppliers', (route) => route.fulfill({ status: 200, json: { data: [] } }))
  await page.route('**/api/v1/editions/ed-27/finance/expenses', (route) => route.fulfill({ status: 200, json: { data, totals: { count: 5, amount: 2_990_000, paid: 1_140_000, pending: 1_850_000 } } }))
  await goto('/financas', { waitUntil: 'hydration' })
  await expect(page.getByTestId('finance-kpis')).toContainText('R$ 20.700')
  await page.screenshot({ path: out('painel-financas') })
})

test('tarefas', async ({ page, goto }) => {
  await page.setViewportSize({ width: 1440, height: 1000 })
  await signedIn(page)
  const card = (id: string, column: string, title: string, who: string, due: string | null, extra: Record<string, unknown> = {}) => ({
    id, column_id: column, position: 0, title, description: null, assignee: { id: `u-${who}`, name: who }, due_on: due, overdue: false, done: false, link: null, updated_at: '2027-03-01T12:00:00Z', ...extra,
  })
  const columns = [
    { id: 'c-1', name: 'A fazer', position: 0, is_done: false, count: 3, tasks: [
      card('t-1', 'c-1', 'Fechar contrato do buffet', 'Lívia Ferraz', '2099-08-15', { link: { type: 'supplier', type_label: 'Fornecedor', id: 'l-1', name: 'Buffet Sabor da Terra' } }),
      card('t-2', 'c-1', 'Pedir arte dos crachás', 'Mariana Sales', '2099-09-01'),
      card('t-3', 'c-1', 'Criar página de patrocinadores', 'João Pedro', '2099-08-30'),
    ] },
    { id: 'c-2', name: 'Fazendo', position: 1, is_done: false, count: 2, tasks: [
      card('t-4', 'c-2', 'Convidar palestrantes de AL e SE', 'João Pedro', new Date(Date.now() - 3 * 86_400_000).toISOString().slice(0, 10), { overdue: true }),
      card('t-5', 'c-2', 'Testar webhook do Asaas', 'Lívia Ferraz', '2099-11-10'),
    ] },
    { id: 'c-3', name: 'Aguardando', position: 2, is_done: false, count: 1, tasks: [card('t-6', 'c-3', 'Texto do e-mail de resultado do CFP', 'Mariana Sales', '2099-04-20')] },
    { id: 'c-4', name: 'Feito', position: 3, is_done: true, count: 2, tasks: [
      card('t-7', 'c-4', 'Reservar o espaço', 'Rita Organiza', null, { done: true }),
      card('t-8', 'c-4', 'Definir identidade visual', 'Rita Organiza', null, { done: true }),
    ] },
  ]
  await page.route('**/api/v1/editions/ed-27/team', (route) => route.fulfill({ status: 200, json: { data: { members: [], invitations: [], summary: {} } } }))
  await page.route('**/api/v1/editions/ed-27/tasks/link-options', (route) => route.fulfill({ status: 200, json: { data: [] } }))
  await page.route('**/api/v1/editions/ed-27/tasks/board**', (route) => route.fulfill({ status: 200, json: { data: { filter: 'all', can_manage: true, columns, totals: { tasks: 8, overdue: 1 } } } }))
  await goto('/tarefas', { waitUntil: 'hydration' })
  await expect(page.getByTestId('task-card').first()).toBeVisible()
  await page.screenshot({ path: out('painel-tarefas') })
})

test('comunicacao', async ({ page, goto }) => {
  await page.setViewportSize({ width: 1440, height: 1000 })
  await signedIn(page)
  const sentAt = (hoursAgo: number) => new Date(Date.now() - hoursAgo * 3_600_000).toISOString()
  const template = (id: string, label: string, trigger: string, lastSent: string | null, extra: Record<string, unknown> = {}) => ({
    id, label, trigger, transactional: true, variables: ['nome', 'edicao', 'pedido', 'data'], action_label: 'Ver meu ingresso',
    subject: 'Seu ingresso para o {edicao}', body: 'Oi, {nome}!\n\nPagamento confirmado (pedido {pedido}). Seu QR code está na sua conta: é só mostrar na entrada.', customized: false, last_sent_at: lastSent, ...extra,
  })
  const templates = [
    template('order_confirmed', 'Confirmação de compra', 'Ao pagar o pedido', sentAt(0.2), { customized: true }),
    template('pix_reminder', 'Lembrete de pagamento', 'Quando a organização reenvia a cobrança', sentAt(1.5)),
    template('cfp_accepted', 'Resultado do CFP: aprovada', 'Manual', null),
    template('cfp_waitlisted', 'Resultado do CFP: lista de espera', 'Manual', null),
    template('event_reminder', 'Antes do evento', '7 dias antes', null, { transactional: false }),
    template('team_invite', 'Convite para a equipe', 'Ao convidar alguém para a equipe', sentAt(30)),
  ]
  const html = `<!doctype html><html><body style="margin:0;background:#FBF4E4;font-family:Lexend,system-ui,sans-serif;color:#3A1607">
<div style="max-width:560px;margin:24px auto;background:#FFFBF2;border:1px solid #E4D3AB;border-radius:14px;overflow:hidden">
<div style="height:8px;background:repeating-linear-gradient(90deg,#E8B544 0 16px,#B4532A 16px 32px)"></div>
<div style="padding:24px"><h1 style="font-size:20px;margin:0 0 12px">Seu ingresso para o PHPeste 2027</h1>
<p>Oi, Ada Lovelace!</p><p>Pagamento confirmado (pedido #PH-0042). Seu QR code está na sua conta: é só mostrar na entrada.</p>
<a style="display:inline-block;margin-top:8px;padding:12px 18px;border-radius:10px;background:#6B2A12;color:#FFF8E8;text-decoration:none">Ver meu ingresso</a>
<p style="font-size:12px;color:#7F5A47;margin-top:20px">PHPeste 2027 · 8 e 9 de outubro de 2027 · João Pessoa</p></div></div></body></html>`
  await page.route('**/api/v1/editions/ed-27/emails/templates', (route) => route.fulfill({ status: 200, json: { data: templates } }))
  await page.route('**/api/v1/editions/ed-27/emails/templates/*/preview', (route) => route.fulfill({ status: 200, json: { data: { subject: 'Seu ingresso para o PHPeste 2027', html, text: '' } } }))
  await goto('/comunicacao', { waitUntil: 'hydration' })
  await expect(page.getByTestId('preview-subject')).toBeVisible()
  await page.screenshot({ path: out('painel-comunicacao') })
})
