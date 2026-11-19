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
