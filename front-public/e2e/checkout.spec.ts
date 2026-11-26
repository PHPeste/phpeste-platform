import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page } from '@playwright/test'

const money = (amount: number) => ({ amount, currency: 'BRL' })
const ticket = (id: string, name: string, price: number, extra: Record<string, unknown> = {}) => ({
  id, name, description: null, min_per_order: 1, max_per_order: 4, pass_fee_to_buyer: true, questions: [], status: 'on_sale', status_label: 'À venda',
  batch: { id: `b-${id}`, name: '1º lote', price: money(price), ends_at: null, remaining: null }, next_batch: null, ...extra,
})
const TICKETS = [
  ticket('t-1', 'Inteira', 15000, { questions: [{ id: 'camiseta', label: 'Tamanho da camiseta', type: 'select', required: true, options: ['P', 'M', 'G'] }], batch: { id: 'b-1', name: '1º lote', price: money(15000), ends_at: null, remaining: 12 } }),
  ticket('t-2', 'Meia', 7500),
  ticket('t-3', 'Estudante', 6000, { status: 'scheduled', status_label: 'Agendado', batch: null, next_batch: { name: '2º lote', price: money(9000), opens_at: '2027-05-01T12:00:00Z' } }),
]

interface Call { method: string, path: string, body: unknown, headers: Record<string, string> }
interface Options { expiresInSeconds?: number, orderStatus?: string, placeError?: Record<string, string[]> }

async function mockApi(page: Page, options: Options = {}) {
  const calls: Call[] = []
  let order: Record<string, unknown> | null = null
  await page.route('**/api/v1/public/editions/ed-a/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname.replace('/api/v1/public/editions/ed-a', '')
    calls.push({ method: request.method(), path, body: request.postDataJSON?.() ?? null, headers: request.headers() })
    if (path === '/tickets') return route.fulfill({ json: { data: { sold_out: false, unlocked: false, ticket_types: TICKETS } } })
    if (path === '/coupons/check') {
      const body = request.postDataJSON() as { code: string, items: Array<{ ticket_type_id: string, quantity: number }> }
      if (body.code !== 'SERTAO10') return route.fulfill({ status: 422, json: { code: 'validation_error', message: 'x', errors: { coupon: ['Cupom não encontrado.'] } } })
      const subtotal = body.items.reduce((sum, item) => sum + item.quantity * (item.ticket_type_id === 't-1' ? 15000 : 7500), 0)
      return route.fulfill({ json: { data: { code: 'SERTAO10', subtotal: money(subtotal), discount: money(subtotal / 10), total: money(subtotal * 0.9), requires_payment: true, lines: [] } } })
    }
    return route.fulfill({ status: 404, json: {} })
  })
  await page.route('**/api/v1/checkout/editions/ed-a/orders**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname.replace('/api/v1/checkout/editions/ed-a/orders', '')
    calls.push({ method: request.method(), path, body: request.postDataJSON?.() ?? null, headers: request.headers() })
    if (request.method() === 'POST' && path === '') {
      if (options.placeError) return route.fulfill({ status: 422, json: { code: 'validation_error', message: 'x', errors: options.placeError } })
      const body = request.postDataJSON() as { buyer: { name: string, email: string }, coupon: string | null, items: Array<{ ticket_type_id: string, attendees: Array<{ name: string, email: string }> }> }
      const items = body.items.flatMap((item) => item.attendees.map((attendee, index) => ({
        id: `i-${item.ticket_type_id}-${index}`, ticket_type_id: item.ticket_type_id, ticket_type_name: item.ticket_type_id === 't-1' ? 'Inteira' : 'Meia', batch_name: '1º lote',
        unit_price: money(item.ticket_type_id === 't-1' ? 15000 : 7500), discount: money(0), fee: money(0), total: money(0), attendee: { name: attendee.name, email: attendee.email },
      })))
      const subtotal = items.reduce((sum, item) => sum + item.unit_price.amount, 0)
      const discount = body.coupon ? subtotal / 10 : 0
      const fee = Math.round((subtotal - discount) * 0.0399)
      order = {
        id: 'o-1', number: 42, status: options.orderStatus ?? 'created', status_label: 'Criado', buyer: { ...body.buyer, document: '***.982.247-**' },
        subtotal: money(subtotal), discount: money(discount), fee: money(fee), total: money(subtotal - discount + fee), refunded: money(0), gateway_fee: money(0), requires_payment: true,
        coupon_code: body.coupon, payment_account_id: 'acc-1', expires_at: new Date(Date.now() + (options.expiresInSeconds ?? 1800) * 1000).toISOString(), paid_at: null, created_at: new Date().toISOString(), items,
      }
      return route.fulfill({ status: 201, json: { data: { ...order, access_token: 'tok-1' } } })
    }
    if (request.headers()['x-order-token'] !== 'tok-1' || !order) return route.fulfill({ status: 404, json: { code: 'not_found', message: 'x' } })
    if (path === '/o-1/cancel') {
      order = { ...order, status: 'cancelled', status_label: 'Cancelado' }
      return route.fulfill({ json: { data: order } })
    }
    return route.fulfill({ json: { data: order } })
  })
  return calls
}

async function fillAndReserve(page: Page) {
  await page.getByLabel('Quantidade de Inteira').selectOption('1')
  await page.getByRole('button', { name: 'Continuar' }).click()
  const buyer = page.getByRole('group', { name: 'Quem paga' })
  await buyer.getByLabel('Nome completo').fill('Ada Lovelace')
  await buyer.getByLabel('E-mail').fill('ada@x.dev')
  await buyer.getByLabel('CPF').fill('52998224725')
  const first = page.getByRole('group', { name: 'Ingresso 1 · Inteira' })
  await first.getByRole('button', { name: 'Usar os dados de quem paga' }).click()
  await first.getByLabel('Tamanho da camiseta').selectOption('M')
  await page.getByRole('button', { name: 'Reservar e ir para o pagamento' }).click()
}

test.describe('Checkout: escolha e dados', () => {
  test('escolhe, aplica cupom, preenche quem vai e reserva; o pedido mostra a contagem e as taxas', async ({ page, goto }) => {
    const calls = await mockApi(page)
    await goto('/ingressos', { waitUntil: 'hydration' })

    await expect(page.getByTestId('ticket-t-1')).toContainText('Restam 12')
    await expect(page.getByTestId('ticket-t-3')).toContainText('2º lote abre em')
    await expect(page.getByLabel('Quantidade de Estudante')).toHaveCount(0)

    await page.getByLabel('Quantidade de Inteira').selectOption('1')
    await page.getByLabel('Quantidade de Meia').selectOption('1')
    await page.getByLabel('Cupom de desconto').fill('errado')
    await page.getByRole('button', { name: 'Aplicar' }).click()
    await expect(page.locator('#checkout-coupon-status')).toHaveText('Cupom não encontrado.')
    await page.getByLabel('Cupom de desconto').fill('sertao10')
    await page.getByRole('button', { name: 'Aplicar' }).click()
    await expect(page.locator('#checkout-coupon-status')).toContainText('Cupom SERTAO10 aplicado')
    await expect(page.getByTestId('summary-total')).toHaveText(/R\$\s202,50/)
    await expect(page.getByTestId('order-summary')).toContainText('calculada ao reservar')

    await page.getByRole('button', { name: 'Continuar' }).click()
    await expect(page.getByRole('heading', { name: 'Ingressos', level: 1 })).toBeFocused()
    const buyer = page.getByRole('group', { name: 'Quem paga' })
    await buyer.getByLabel('Nome completo').fill('Ada Lovelace')
    await buyer.getByLabel('E-mail').fill('ada@x.dev')
    await buyer.getByLabel('CPF').fill('52998224725')
    await expect(buyer.getByLabel('CPF')).toHaveValue('529.982.247-25')
    const first = page.getByRole('group', { name: 'Ingresso 1 · Inteira' })
    await first.getByRole('button', { name: 'Usar os dados de quem paga' }).click()
    await first.getByLabel('Tamanho da camiseta').selectOption('G')
    const second = page.getByRole('group', { name: 'Ingresso 2 · Meia' })
    await second.getByLabel('Nome completo').fill('Grace Hopper')
    await second.getByLabel('E-mail').fill('grace@x.dev')
    await page.getByRole('button', { name: 'Reservar e ir para o pagamento' }).click()

    await expect(page).toHaveURL(/\/checkout\/pedido\/o-1$/)
    await expect(page.getByRole('timer')).toHaveText(/^(29|30):\d{2}$/)
    await expect(page.getByTestId('order-totals')).toContainText('Desconto (SERTAO10)')
    await expect(page.getByTestId('order-totals')).toContainText(/Taxa de serviço\s*R\$\s8,08/)
    await expect(page.getByTestId('order-totals')).toContainText(/Total\s*R\$\s210,58/)

    const placed = calls.find((call) => call.method === 'POST' && call.path === '')!
    expect(placed.headers['idempotency-key']).toMatch(/.{8,}/)
    expect(placed.body).toEqual({
      buyer: { name: 'Ada Lovelace', email: 'ada@x.dev', document: '529.982.247-25' },
      coupon: 'SERTAO10',
      items: [
        { ticket_type_id: 't-1', attendees: [{ name: 'Ada Lovelace', email: 'ada@x.dev', answers: { camiseta: 'G' } }] },
        { ticket_type_id: 't-2', attendees: [{ name: 'Grace Hopper', email: 'grace@x.dev', answers: {} }] },
      ],
    })
  })

  test('campos com erro aparecem num resumo com links, e o erro do backend vai para o campo', async ({ page, goto }) => {
    await mockApi(page, { placeError: { buyer_document: ['Informe o CPF de quem paga.'] } })
    await goto('/ingressos', { waitUntil: 'hydration' })
    await page.getByLabel('Quantidade de Inteira').selectOption('1')
    await page.getByRole('button', { name: 'Continuar' }).click()
    await page.getByRole('button', { name: 'Reservar e ir para o pagamento' }).click()

    const summary = page.getByTestId('checkout-errors')
    await expect(summary).toBeFocused()
    await expect(summary).toContainText('Falta corrigir 6 campos antes de reservar.')
    await summary.getByRole('link', { name: 'Ingresso 1 · Inteira: Tamanho da camiseta' }).click()
    await expect(page.getByRole('group', { name: 'Ingresso 1 · Inteira' }).getByLabel('Tamanho da camiseta')).toBeFocused()

    await page.getByRole('button', { name: 'Voltar para a escolha' }).click()
    await fillAndReserve(page)
    await expect(page.getByTestId('checkout-errors')).toContainText('Quem paga: CPF: Informe o CPF de quem paga.')
    await expect(page).toHaveURL(/\/ingressos$/)
  })

  test('reserva expirada volta para a escolha com a mensagem e a escolha anterior', async ({ page, goto }) => {
    await mockApi(page, { expiresInSeconds: 3 })
    await goto('/ingressos', { waitUntil: 'hydration' })
    await fillAndReserve(page)

    await expect(page.getByRole('timer')).toBeVisible()
    await expect(page).toHaveURL(/\/ingressos\?reserva=expirada$/, { timeout: 10_000 })
    await expect(page.getByTestId('checkout-notice')).toHaveText('Sua reserva expirou e os ingressos voltaram para a venda. Escolha de novo para garantir os seus.')
    await expect(page.getByLabel('Quantidade de Inteira')).toHaveValue('1')
  })

  test('desistir libera os ingressos e volta para a escolha', async ({ page, goto }) => {
    const calls = await mockApi(page)
    await goto('/ingressos', { waitUntil: 'hydration' })
    await fillAndReserve(page)
    await page.getByRole('button', { name: 'Desistir e liberar os ingressos' }).click()
    await page.getByRole('group', { name: /Desistir da reserva/ }).getByRole('button', { name: 'Desistir e liberar os ingressos' }).click()

    await expect(page).toHaveURL(/\/ingressos\?reserva=cancelada$/)
    await expect(page.getByTestId('checkout-notice')).toContainText('Você desistiu da reserva')
    expect(calls.some((call) => call.path === '/o-1/cancel')).toBe(true)
  })

  test('só com teclado em 360 px, sem rolagem lateral', async ({ page, goto }) => {
    await page.setViewportSize({ width: 360, height: 740 })
    await mockApi(page)
    await goto('/ingressos', { waitUntil: 'hydration' })
    const noSideScroll = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)
    expect(await noSideScroll()).toBe(true)

    await page.getByLabel('Quantidade de Inteira').focus()
    await page.keyboard.press('1')
    await expect(page.getByLabel('Quantidade de Inteira')).toHaveValue('1')
    await page.getByRole('button', { name: 'Continuar' }).focus()
    await page.keyboard.press('Enter')
    await expect(page.getByRole('heading', { name: 'Ingressos', level: 1 })).toBeFocused()
    expect(await noSideScroll()).toBe(true)

    const typeInto = async (label: string, text: string) => {
      while (!(await page.evaluate((name) => document.activeElement?.id !== '' && document.querySelector(`label[for="${document.activeElement?.id}"]`)?.textContent?.trim().startsWith(name), label))) {
        await page.keyboard.press('Tab')
      }
      await page.keyboard.type(text)
    }
    await typeInto('Nome completo', 'Ada Lovelace')
    await typeInto('E-mail', 'ada@x.dev')
    await typeInto('CPF', '52998224725')
    await page.keyboard.press('Tab')
    await expect(page.getByRole('button', { name: 'Usar os dados de quem paga' })).toBeFocused()
    await page.keyboard.press('Enter')
    await page.keyboard.press('Tab')
    await page.keyboard.press('Tab')
    await page.keyboard.press('Tab')
    await expect(page.getByLabel('Tamanho da camiseta')).toBeFocused()
    await page.keyboard.press('M')
    await expect(page.getByLabel('Tamanho da camiseta')).toHaveValue('M')
    await page.keyboard.press('Tab')
    await expect(page.getByRole('button', { name: 'Reservar e ir para o pagamento' })).toBeFocused()
    await page.keyboard.press('Enter')

    await expect(page).toHaveURL(/\/checkout\/pedido\/o-1$/)
    await expect(page.getByRole('timer')).toBeVisible()
    expect(await noSideScroll()).toBe(true)
  })
})
