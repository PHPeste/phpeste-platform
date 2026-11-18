import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page } from '@playwright/test'

const session = { user: { id: 'user-1', name: 'Nome Sobrenome', email: 'n@s.dev', avatarUrl: null }, token: 'e2e-token' }

const edition = {
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHPeste', slug: 'phpeste' },
  status: 'cfp_open', status_label: 'CFP aberto', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB',
  year: 2027, site_url: null, role: 'organizador',
}

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

async function openOverview(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>) {
  const calls: string[] = []
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition] } }))
  await page.route('**/api/v1/editions/ed-27/**', (route) => {
    calls.push(new URL(route.request().url()).pathname)
    return route.request().url().endsWith('/overview') ? route.fulfill({ status: 200, json: { data: overview } }) : route.fulfill({ status: 404, json: {} })
  })
  await goto('/', { waitUntil: 'hydration' })
  return calls
}

test.describe('Visão geral', () => {
  test('mostra etapa, indicadores, pendências, Nordeste e marcos a partir de um endpoint só', async ({ page, goto }) => {
    const calls = await openOverview(page, goto)

    await expect(page.getByRole('heading', { name: 'Visão geral', level: 1 })).toBeVisible()
    await expect(page.getByText('PHPeste 2027 · 8 e 9 de outubro de 2027 · João Pessoa (PB)')).toBeVisible()
    await expect(page.getByText('Faltam 339 dias para o evento')).toBeVisible()
    await expect(page.locator('[aria-current="step"]')).toContainText('CFP aberto')

    const indicators = page.getByRole('region', { name: 'Indicadores' })
    await expect(indicators).toContainText('128 de 400 disponíveis')
    await expect(indicators).toContainText('31 já avaliadas')
    await expect(indicators).toContainText('meta de 24 na grade')

    await expect(page.getByText('62% dos confirmados são do Nordeste')).toBeVisible()
    await expect(page.getByText('Alagoas')).toBeVisible()
    await expect(page.getByText('15 abr 2027')).toBeVisible()

    expect(calls).toEqual(['/api/v1/editions/ed-27/overview'])
  })

  test('cada pendência leva à tela certa com o filtro aplicado', async ({ page, goto }) => {
    await openOverview(page, goto)

    await expect(page.getByRole('link', { name: 'Atribuir: 7 propostas sem revisor' })).toHaveAttribute('href', '/cfp/avaliacao?filtro=sem-revisor')
    await expect(page.getByRole('link', { name: 'Ver palestrantes: 2 palestrantes sem resposta à confirmação' })).toHaveAttribute('href', '/palestrantes?confirmacao=sem-resposta')
  })

  test('gráfico acessível: resumo, valor no foco por teclado e tabela equivalente', async ({ page, goto }) => {
    await openOverview(page, goto)

    const chart = page.getByRole('img', { name: /Ingressos vendidos por semana nas últimas 8 semanas/ })
    await expect(chart).toBeVisible()

    await page.getByRole('img').locator('rect[aria-label="sem 7: 55 ingressos"]').focus()
    await expect(page.getByRole('status').filter({ hasText: 'sem 7' })).toHaveText('55 · sem 7')

    await page.getByRole('button', { name: 'Ver como tabela' }).click()
    const table = page.getByRole('table', { name: 'Ingressos por semana' })
    await expect(table.getByRole('row')).toHaveCount(9)
    await expect(table.getByRole('row', { name: 'sem 7 55' })).toBeVisible()
  })
})
