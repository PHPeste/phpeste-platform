import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'u-rita', name: 'Rita Organiza', email: 'rita@php-pb.net', avatarUrl: null }, token: 'e2e-token' }
const edition = (role: string) => ({
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHP-PB', slug: 'php-pb' },
  status: 'sales_open', status_label: 'Vendas abertas', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role,
})
const template = (id: string, label: string, extra: Record<string, unknown> = {}) => ({
  id, label, trigger: 'Ao pagar o pedido', transactional: true, variables: ['nome', 'edicao', 'pedido', 'data'], action_label: 'Ver meu ingresso',
  subject: 'Seu ingresso para o {edicao}', body: 'Oi, {nome}!\n\nPedido {pedido}.', customized: false, last_sent_at: null, ...extra,
})
const SAMPLE = { nome: 'Ada Lovelace', edicao: 'PHPeste 2027', pedido: '#PH-0042', data: '8 e 9 de outubro' } as Record<string, string>
const render = (text: string) => text.replace(/\{([a-z_]+)\}/g, (match, name: string) => SAMPLE[name] ?? match)

interface Call { method: string, path: string, body: unknown }

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, role = 'organizador', handle: (route: Route, call: Call) => boolean = () => false) {
  const calls: Call[] = []
  const templates = [
    template('order_confirmed', 'Confirmação de compra', { last_sent_at: new Date().toISOString() }),
    template('event_reminder', 'Antes do evento', { trigger: '7 dias antes', transactional: false, variables: ['nome', 'edicao', 'data'], subject: 'Falta pouco: {edicao}', body: 'Oi, {nome}! Até {data}.', customized: true, action_label: 'Ver programação' }),
  ]
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition(role)] } }))
  await page.route('**/api/v1/editions/ed-27/emails/**', (route) => {
    const request = route.request()
    const call = { method: request.method(), path: new URL(request.url()).pathname.replace('/api/v1/editions/ed-27/emails/templates', ''), body: request.postDataJSON?.() ?? null }
    calls.push(call)
    if (handle(route, call)) return
    if (call.path === '') return route.fulfill({ status: 200, json: { data: templates } })
    const [, id, action] = call.path.split('/')
    const index = templates.findIndex((entry) => entry.id === id)
    const body = call.body as { subject: string, body: string }
    if (action === 'preview') {
      return route.fulfill({ status: 200, json: { data: { subject: render(body.subject), html: `<html><body><h1>${render(body.subject)}</h1><p>${render(body.body)}</p></body></html>`, text: '' } } })
    }
    if (action === 'test') return route.fulfill({ status: 202, json: { data: { id: 'd-1', to_email: 'rita@php-pb.net', status: 'queued', status_label: 'Na fila' } } })
    if (call.method === 'PUT') templates[index] = { ...templates[index]!, ...body, customized: true }
    if (call.method === 'DELETE') templates[index] = { ...templates[index]!, subject: 'Falta uma semana para o {edicao}', customized: false }
    return route.fulfill({ status: 200, json: { data: templates[index] } })
  })
  await goto('/comunicacao', { waitUntil: 'hydration' })
  return calls
}

const preview = (page: Page) => page.frameLocator('iframe[title^="Pré-visualização do e-mail"]')

test.describe('Comunicação', () => {
  test('lista com gatilho e último envio; prévia com os dados de exemplo do backend', async ({ page, goto }) => {
    await open(page, goto)
    await expect(page.getByRole('heading', { name: 'Comunicação', level: 1 })).toBeVisible()
    await expect(page.getByTestId('template-order_confirmed')).toContainText(/hoje, \d{2}:\d{2}/)
    await expect(page.getByTestId('template-event_reminder')).toContainText('nunca')
    await expect(page.getByTestId('template-event_reminder')).toContainText('Personalizado')

    await expect(page.getByTestId('preview-subject')).toHaveText('Assunto: Seu ingresso para o PHPeste 2027')
    await expect(preview(page).locator('p')).toContainText('Ada Lovelace')

    await page.getByLabel('Assunto').fill('Pronto, {nome}: {edicao}')
    await expect(page.getByTestId('preview-subject')).toHaveText('Assunto: Pronto, Ada Lovelace: PHPeste 2027')
  })

  test('variável inválida bloqueia salvar com mensagem clara; corrigida, salva', async ({ page, goto }) => {
    const calls = await open(page, goto)
    await page.getByLabel('Mensagem').fill('Oi, {nome}! Seu {ingresso} chegou.')
    await expect(page.getByText('Variável desconhecida no modelo: {ingresso}. Use só {nome}, {edicao}, {pedido}, {data}.')).toBeVisible()
    await expect(page.getByText('Corrija o assunto e a mensagem para ver a prévia.')).toBeVisible()

    await page.getByRole('button', { name: 'Salvar modelo' }).click()
    await expect(page.getByTestId('form-errors')).toContainText('Mensagem')
    expect(calls.some((call) => call.method === 'PUT')).toBe(false)

    await page.getByLabel('Mensagem').fill('Oi, {nome}! Seu pedido {pedido} chegou.')
    await page.getByRole('button', { name: 'Salvar modelo' }).click()
    await expect(page.getByText('Modelo salvo.')).toBeVisible()
    await expect(page.getByTestId('template-order_confirmed')).toContainText('Personalizado')
    expect(calls.find((call) => call.method === 'PUT')?.body).toEqual({ subject: 'Seu ingresso para o {edicao}', body: 'Oi, {nome}! Seu pedido {pedido} chegou.' })
  })

  test('insere variável pelo botão e envia teste para si mesmo com o texto da tela', async ({ page, goto }) => {
    const calls = await open(page, goto)
    const subject = page.getByLabel('Assunto')
    await subject.fill('Ingresso ')
    await subject.press('End')
    await page.getByRole('button', { name: 'Inserir {nome}' }).click()
    await expect(subject).toHaveValue('Ingresso {nome}')
    await expect(subject).toBeFocused()

    await page.getByRole('button', { name: 'Enviar teste para mim' }).click()
    await expect(page.getByText('Teste enviado para rita@php-pb.net.')).toBeVisible()
    expect(calls.find((call) => call.path.endsWith('/test'))?.body).toMatchObject({ subject: 'Ingresso {nome}' })
  })

  test('trocar de modelo com alteração pede confirmação; restaurar o padrão', async ({ page, goto }) => {
    const calls = await open(page, goto)
    await page.getByLabel('Assunto').fill('Mudei {edicao}')
    await page.getByRole('button', { name: 'Editar Antes do evento' }).click()
    const discard = page.getByRole('dialog', { name: 'Descartar as alterações em "Confirmação de compra"?' })
    await discard.getByRole('button', { name: 'Descartar e trocar' }).click()
    await expect(page.getByRole('heading', { name: 'Antes do evento', level: 2, exact: true })).toBeVisible()

    await page.getByRole('button', { name: 'Restaurar padrão' }).click()
    await page.getByRole('dialog', { name: /Restaurar o texto padrão/ }).getByRole('button', { name: 'Restaurar padrão' }).click()
    await expect(page.getByLabel('Assunto')).toHaveValue('Falta uma semana para o {edicao}')
    expect(calls.some((call) => call.method === 'DELETE' && call.path === '/event_reminder')).toBe(true)
  })

  test('quem não organiza não abre a tela', async ({ page, goto }) => {
    const calls = await open(page, goto, 'financeiro')
    await expect(page.getByRole('heading', { name: 'Sem permissão' })).toBeVisible()
    expect(calls).toHaveLength(0)
  })
})
