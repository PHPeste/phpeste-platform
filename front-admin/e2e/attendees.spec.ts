import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Rita Organiza', email: 'rita@php-pb.net', avatarUrl: null }, token: 'e2e-token' }
const edition = (role: string) => ({
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHP-PB', slug: 'php-pb' },
  status: 'sales_open', status_label: 'Vendas abertas', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role,
})
const ANA = '0e1c6d1a-0000-4000-8000-0000000000a1'
const CARLA = '0e1c6d1a-0000-4000-8000-0000000000c1'
const ticket = (id: string, holder: Record<string, unknown>, extra: Record<string, unknown> = {}) => ({
  id, code: id === ANA ? 'K7QM-4XTD' : 'P2RW-8YHN', status: 'active', status_label: 'Válido', order_id: 'o-1', order_number: 42, ticket_type_id: 'tt-1', ticket_type_name: 'Geral', batch_name: '1º lote',
  holder, missing: [], transferred_from: null, issued_at: '2027-06-10T12:00:00Z', cancelled_at: null, last_sent_at: '2027-06-10T12:00:00Z', ...extra,
})
const ana = (accessibility: boolean) => ticket(ANA, { name: 'Ana Souza', email: 'ana@exemplo.com', badge_name: 'Aninha', company: 'PHP-PB', tshirt_size: 'BL-M', ...(accessibility ? { accessibility: 'Intérprete de Libras' } : {}) })
const carla = (accessibility: boolean) => ticket(CARLA, { name: 'Carla Dias', email: 'carla@exemplo.com', badge_name: null, company: null, tshirt_size: null, ...(accessibility ? { accessibility: null } : {}) }, { missing: ['badge_name', 'tshirt_size'], transferred_from: 'Bia Lima' })
const summary = (accessibility: boolean) => ({ participants: 2, complete: 1, missing: 1, accessibility: accessibility ? 1 : null, transferred: 1 })

interface Calls { method: string, path: string, query: string, body: unknown }

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, role = 'organizador', handle: (route: Route, call: Calls) => Promise<boolean> | boolean = () => false) {
  const calls: Calls[] = []
  const priv = role === 'organizador'
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition(role)] } }))
  await page.route('**/api/v1/editions/ed-27/ticketing/tickets**', async (route) => {
    const url = new URL(route.request().url())
    const call = { method: route.request().method(), path: url.pathname.replace('/api/v1/editions/ed-27/ticketing/tickets', ''), query: url.search, body: route.request().postDataJSON() }
    calls.push(call)
    if (await handle(route, call)) return
    if (call.path === '') {
      const data = url.searchParams.get('q') === 'ninguem' ? [] : url.searchParams.get('missing') ? [carla(priv)] : [ana(priv), carla(priv)]
      return route.fulfill({ status: 200, json: { data, meta: { current_page: 1, last_page: 1, total: data.length, summary: summary(priv) } } })
    }
    if (call.path === '/badges') return route.fulfill({ status: 200, headers: { 'Content-Type': 'text/csv' }, body: '﻿Código;Nome no crachá\n' })
    if (call.path === '/data-reminder' && call.method === 'GET') {
      return route.fulfill({ status: 200, json: { data: { recipients: 1, email: { subject: 'Complete seus dados para o crachá do PHPeste 2027', html: '<p>Oi, Carla Dias!</p>', text: 'Oi' } } } })
    }
    if (call.path === '/data-reminder') return route.fulfill({ status: 202, json: { data: { recipients: 1 } } })
    const base = call.path.includes(CARLA) ? carla(priv) : ana(priv)
    const edits = call.method === 'PATCH'
      ? [{ id: 'e-1', changes: [{ field: 'badge_name', label: 'Nome no crachá', from: null, to: 'Carlinha' }], actor: { id: 'u-me', name: 'Rita Organiza' }, at: '2027-06-11T12:00:00Z' }]
      : []
    const body = call.method === 'PATCH' ? { ...base, holder: { ...base.holder, badge_name: 'Carlinha', tshirt_size: 'M' }, missing: [] } : base
    return route.fulfill({ status: 200, json: { data: { ...body, transfers: call.path.includes(CARLA) ? [{ from: { name: 'Bia Lima', email: 'bia@exemplo.com' }, to: { name: 'Carla Dias', email: 'carla@exemplo.com' }, channel: 'holder', transferred_by: null, at: '2027-06-10T13:00:00Z' }] : [], edits } } })
  })
  await goto('/participantes', { waitUntil: 'hydration' })
  return calls
}

test.describe('Participantes', () => {
  test('lista uma linha por ingresso com o resumo e filtra quem está com dados faltando', async ({ page, goto }) => {
    const calls = await open(page, goto)
    await expect(page.getByRole('heading', { name: 'Participantes', level: 1 })).toBeVisible()

    const summaryBox = page.getByTestId('attendees-summary')
    await expect(summaryBox).toContainText('Com crachá completo')
    await expect(summaryBox).toContainText('1 faltam dados')
    await expect(summaryBox).toContainText('Acessibilidade')
    await expect(page.getByRole('row', { name: /Ana Souza/ })).toContainText('Aninha · PHP-PB')
    await expect(page.getByRole('row', { name: /Ana Souza/ })).toContainText('Intérprete de Libras')
    await expect(page.getByRole('row', { name: /Carla Dias/ })).toContainText('Transferido de Bia Lima')
    await expect(page.getByRole('row', { name: /Carla Dias/ })).toContainText('Falta o crachá')
    await expect(page.getByText('Mostrando 2 de 2 participantes')).toBeVisible()

    await page.getByRole('group', { name: 'Filtrar participantes' }).getByRole('button', { name: 'Dados faltando' }).click()
    await expect(page.getByRole('row', { name: /Ana Souza/ })).toHaveCount(0)
    expect(calls.some((call) => call.path === '' && call.query.includes('missing=1'))).toBe(true)

    await page.getByRole('searchbox', { name: 'Buscar participante' }).fill('ninguem')
    await page.getByRole('searchbox', { name: 'Buscar participante' }).press('Enter')
    await expect(page.getByText('Ninguém com esse filtro.')).toBeVisible()
  })

  test('edita o crachá no detalhe, com a acessibilidade marcada como só da organização e a alteração registrada', async ({ page, goto }) => {
    const calls = await open(page, goto)
    await page.getByRole('button', { name: 'Abrir participante Carla Dias' }).click()

    const detail = page.getByTestId('attendee-detail')
    await expect(detail).toContainText('Bia Lima → Carla Dias')
    await expect(detail).toContainText('Visível só para a organização')
    await expect(detail).toContainText('Nenhuma alteração ainda.')

    await page.getByLabel('Nome no crachá').fill('Carlinha')
    await page.getByLabel('Camiseta').selectOption('M')
    await page.getByRole('button', { name: 'Salvar dados' }).click()

    await expect(page.getByTestId('attendee-edits')).toContainText('Nome no crachá: vazio → Carlinha')
    await expect(page.getByTestId('attendee-edits')).toContainText('por Rita Organiza')
    expect(calls.find((call) => call.method === 'PATCH')?.body).toMatchObject({ badge_name: 'Carlinha', tshirt_size: 'M', accessibility: null })
  })

  test('cobra os dados faltando com prévia do e-mail e confirma o número de pessoas', async ({ page, goto }) => {
    const calls = await open(page, goto)
    await page.getByRole('button', { name: 'Cobrar dados faltando' }).click()

    const preview = page.getByTestId('reminder-preview')
    await expect(preview).toContainText('1 pessoa com ingresso válido')
    await expect(preview).toContainText('Assunto: Complete seus dados para o crachá do PHPeste 2027')
    await expect(page.frameLocator('iframe[title="Prévia do e-mail"]').getByText('Oi, Carla Dias!')).toBeVisible()

    await page.getByRole('button', { name: 'Enviar para 1 pessoa' }).click()
    await expect(page.getByText('Cobrança enviada para 1 pessoa.')).toBeVisible()
    expect(calls.find((call) => call.method === 'POST' && call.path === '/data-reminder')?.body).toEqual({ confirm: true, recipients: 1 })
  })

  test('exporta o CSV dos crachás', async ({ page, goto }) => {
    await open(page, goto)
    const download = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Exportar crachás' }).click()
    expect((await download).suggestedFilename()).toMatch(/^crachas-\d{4}-\d{2}-\d{2}\.csv$/)
  })

  test('check-in não acessa a lista', async ({ page, goto }) => {
    const calls = await open(page, goto, 'checkin')
    await expect(page.getByText('A lista de participantes é da organização.')).toBeVisible()
    expect(calls).toHaveLength(0)
  })
})
