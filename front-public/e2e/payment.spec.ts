import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Request } from '@playwright/test'

const money = (amount: number) => ({ amount, currency: 'BRL' })
const CARD = '4111111111111111'

const FAKE_MP_SDK = `
window.MercadoPago = class {
  constructor() {
    const labels = { cardNumber: 'Número do cartão', expirationDate: 'Validade', securityCode: 'Código de segurança' }
    this.fields = {
      create: (type) => {
        const field = {
          mount(id) {
            this.el = document.createElement('input')
            this.el.setAttribute('aria-label', labels[type])
            this.el.dataset.mp = type
            document.getElementById(id).appendChild(this.el)
            if (type === 'cardNumber') this.el.addEventListener('input', () => {
              const digits = this.el.value.replace(/\\D/g, '')
              if (digits.length >= 8 && this.bin) this.bin({ bin: digits.slice(0, 8) })
            })
            return this
          },
          on(event, handler) { this.bin = handler; return this },
          unmount() { this.el && this.el.remove() },
        }
        return field
      },
      createCardToken: async () => {
        const digits = document.querySelector('[data-mp=cardNumber]').value.replace(/\\D/g, '')
        if (digits.length < 16) throw new Error('invalid')
        return { id: 'tok_fake_123' }
      },
    }
  }
  async getInstallments({ amount }) {
    const total = Number(amount)
    return [{ payment_method_id: 'visa', issuer: { id: 25 }, payer_costs: [1, 2, 3, 6].map((n) => ({ installments: n, installment_amount: Math.round((total / n) * 100) / 100, total_amount: total, recommended_message: '' })) }]
  }
}`

interface Options { driver?: 'asaas' | 'mercadopago', decline?: boolean, paidAfterPolls?: number }

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, options: Options = {}) {
  const state = { charges: [] as Array<Record<string, unknown>>, polls: 0, paid: false }
  const calls: Array<{ method: string, path: string, body: unknown, headers: Record<string, string> }> = []
  const order = () => ({
    id: 'o-1', number: 42, status: state.paid ? 'paid' : state.charges.length ? 'awaiting_payment' : 'created', status_label: '', buyer: { name: 'Ada Lovelace', email: 'ada@x.dev', document: '***' },
    subtotal: money(30000), discount: money(0), fee: money(1080), total: money(31080), refunded: money(0), gateway_fee: money(0), requires_payment: true, coupon_code: null, payment_account_id: 'acc-1',
    expires_at: new Date(Date.now() + 25 * 60_000).toISOString(), paid_at: null, created_at: new Date().toISOString(),
    items: [
      { id: 'i-1', ticket_type_id: 't-1', ticket_type_name: 'Inteira', batch_name: '1º lote', unit_price: money(15000), discount: money(0), fee: money(540), total: money(15540), attendee: { name: 'Ada Lovelace', email: 'ada@x.dev' } },
      { id: 'i-2', ticket_type_id: 't-1', ticket_type_name: 'Inteira', batch_name: '1º lote', unit_price: money(15000), discount: money(0), fee: money(540), total: money(15540), attendee: { name: 'Grace Hopper', email: 'grace@x.dev' } },
    ],
  })
  const charge = (body: { method: string, installments: number }) => ({
    id: `c-${state.charges.length + 1}`, method: body.method, status: options.decline ? 'failed' : 'pending', status_label: '', amount: money(31080), installments: body.installments,
    pix: body.method === 'pix' ? { copy_paste: '00020126pix-copia-e-cola-42', qr_code_base64: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' } : null,
    checkout_url: body.method === 'card' && options.driver !== 'mercadopago' ? 'https://pagamento.exemplo.test/fatura/42' : null,
    expires_at: new Date(Date.now() + 25 * 60_000).toISOString(), paid_at: null, failure_reason: options.decline ? 'Cartão recusado pelo banco emissor. Tente outro cartão.' : null, order_status: 'awaiting_payment',
  })

  await page.addInitScript(() => localStorage.setItem('phpeste.checkout.order.o-1', 'tok-1'))
  await page.route('https://sdk.mercadopago.com/**', (route) => route.fulfill({ contentType: 'text/javascript', body: FAKE_MP_SDK }))
  await page.route('https://pagamento.exemplo.test/**', (route) => route.fulfill({ contentType: 'text/html', body: '<h1>Fatura do gateway</h1>' }))
  await page.route('**/api/v1/public/editions/ed-a/payments/receiver', (route) => route.fulfill({ json: { data: {
    driver: options.driver ?? 'asaas', public_key: options.driver === 'mercadopago' ? 'TEST-pk' : null, holder_name: 'Fulana de Tal', holder_type: 'individual', methods: ['pix', 'card'], max_installments: 3, pix_expiration_minutes: 30, sandbox: false, ready: true,
  } } }))
  await page.route('**/api/v1/checkout/editions/ed-a/orders/o-1**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname.replace('/api/v1/checkout/editions/ed-a/orders/o-1', '')
    calls.push({ method: request.method(), path, body: request.postDataJSON?.() ?? null, headers: request.headers() })
    if (request.headers()['x-order-token'] !== 'tok-1') return route.fulfill({ status: 404, json: {} })
    if (path === '/payment' && request.method() === 'POST') {
      await new Promise((resolve) => setTimeout(resolve, 300))
      const created = charge(request.postDataJSON())
      state.charges.push(created)
      return route.fulfill({ json: { data: created } })
    }
    if (path === '/payment') {
      const current = state.charges.at(-1)
      if (!current) return route.fulfill({ json: { data: null } })
      state.polls++
      if (state.polls >= (options.paidAfterPolls ?? 1)) {
        state.paid = true
        return route.fulfill({ json: { data: { ...current, status: 'paid', order_status: 'paid' } } })
      }
      return route.fulfill({ json: { data: current } })
    }
    if (path === '/tickets') {
      return route.fulfill({ json: { data: state.paid ? [
        { id: 'tk-1', code: 'K7QM-4XTD', ticket_type_name: 'Inteira', batch_name: '1º lote', holder: { name: 'Ada Lovelace', email: 'ada@x.dev' }, qr: 'PHT1.tk-1.1.assinatura' },
        { id: 'tk-2', code: 'P2ZR-8WNA', ticket_type_name: 'Inteira', batch_name: '1º lote', holder: { name: 'Grace Hopper', email: 'grace@x.dev' }, qr: 'PHT1.tk-2.1.assinatura' },
      ] : [] } })
    }
    return route.fulfill({ json: { data: order() } })
  })
  await goto('/checkout/pedido/o-1', { waitUntil: 'hydration' })
  return calls
}

test.describe('Checkout: pagamento', () => {
  test('Pix: quem recebe, duplo clique gera uma cobrança só, copia e cola, e o pagamento aprovado mostra os ingressos com QR', async ({ page, goto, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write'])
    const calls = await open(page, goto, { paidAfterPolls: 2 })

    await expect(page.getByTestId('receiver')).toContainText('Pagamento para Fulana de Tal, responsável financeiro do PHPeste 2027.')
    await page.getByRole('button', { name: /Gerar Pix de R\$\s310,80/ }).dblclick()
    await expect(page.getByRole('img', { name: /QR code do Pix de R\$\s310,80/ })).toBeVisible()
    await expect(page.getByLabel('Pix copia e cola')).toHaveValue('00020126pix-copia-e-cola-42')
    await expect(page.getByTestId('pix-payment')).toContainText(/O código vale até \d{2}:\d{2}/)

    await page.getByRole('button', { name: 'Copiar código' }).click()
    await expect(page.getByTestId('pix-copy-status')).toHaveText('Código copiado.')
    expect(await page.evaluate(() => navigator.clipboard.readText())).toBe('00020126pix-copia-e-cola-42')

    await expect(page.getByRole('heading', { name: 'Pagamento aprovado', level: 1 })).toBeVisible({ timeout: 15_000 })
    await expect(page.getByTestId('not-invoice')).toHaveText('Este comprovante não é nota fiscal.')
    await expect(page.getByRole('img', { name: 'QR code do ingresso de Ada Lovelace' })).toBeVisible()
    await expect(page.getByRole('img', { name: 'QR code do ingresso de Grace Hopper' })).toBeVisible()
    await expect(page.getByRole('timer')).toHaveCount(0)

    const posts = calls.filter((call) => call.method === 'POST' && call.path === '/payment')
    expect(posts).toHaveLength(1)
    expect(posts[0]!.body).toEqual({ method: 'pix', installments: 1 })
    expect(posts[0]!.headers['idempotency-key']).toMatch(/.{8,}/)
  })

  test('cartão no formulário seguro: o número do cartão nunca vai para o nosso domínio', async ({ page, goto }) => {
    const ours: Request[] = []
    page.on('request', (request) => {
      if (!request.url().startsWith('https://sdk.mercadopago.com')) ours.push(request)
    })
    const calls = await open(page, goto, { driver: 'mercadopago' })

    await page.getByRole('radio', { name: /Cartão de crédito/ }).check()
    await page.getByRole('textbox', { name: 'Número do cartão' }).fill(CARD)
    await page.getByRole('textbox', { name: 'Validade' }).fill('12/30')
    await page.getByRole('textbox', { name: 'Código de segurança' }).fill('123')
    await page.getByLabel('Nome impresso no cartão').fill('ADA LOVELACE')
    await page.getByLabel('CPF do titular do cartão').fill('52998224725')
    await page.getByLabel('Parcelas').selectOption('3')
    await expect(page.getByLabel('Parcelas')).toContainText(/3× de R\$\s103,60/)
    await page.getByRole('button', { name: /Pagar R\$\s310,80/ }).click()

    await expect(page.getByRole('heading', { name: 'Pagamento aprovado', level: 1 })).toBeVisible({ timeout: 15_000 })
    const post = calls.find((call) => call.method === 'POST' && call.path === '/payment')!
    expect(post.body).toEqual({ method: 'card', installments: 3, card_token: 'tok_fake_123', card_brand: 'visa', card_issuer: '25' })
    for (const request of ours) {
      expect(`${request.url()} ${request.postData() ?? ''}`).not.toContain(CARD)
      expect(`${request.url()} ${request.postData() ?? ''}`).not.toContain('4111 1111')
      expect(request.postData() ?? '').not.toContain('ADA LOVELACE')
    }
  })

  test('cartão recusado mostra o motivo e deixa tentar de novo', async ({ page, goto }) => {
    await open(page, goto, { driver: 'mercadopago', decline: true })
    await page.getByRole('radio', { name: /Cartão de crédito/ }).check()
    await page.getByRole('textbox', { name: 'Número do cartão' }).fill(CARD)
    await page.getByLabel('Nome impresso no cartão').fill('ADA LOVELACE')
    await page.getByLabel('CPF do titular do cartão').fill('52998224725')
    await page.getByRole('button', { name: /Pagar/ }).click()

    const declined = page.getByTestId('payment-declined')
    await expect(declined).toContainText('Pagamento recusado')
    await expect(declined).toContainText('Cartão recusado pelo banco emissor. Tente outro cartão.')
    await declined.getByRole('button', { name: 'Tentar de novo' }).click()
    await expect(page.getByRole('group', { name: 'Como você quer pagar' })).toBeVisible()
  })

  test('cartão na página do gateway: escolhe as parcelas e segue para a fatura', async ({ page, goto }) => {
    const calls = await open(page, goto, { driver: 'asaas' })
    await page.getByRole('radio', { name: /Cartão de crédito/ }).check()
    await expect(page.getByTestId('hosted-card')).toContainText('Os dados do cartão não passam pela PHPeste.')
    await page.getByLabel('Parcelas').selectOption('2')
    await page.getByRole('button', { name: 'Ir para o pagamento com cartão' }).click()

    await expect(page).toHaveURL('https://pagamento.exemplo.test/fatura/42')
    expect(calls.find((call) => call.method === 'POST')?.body).toEqual({ method: 'card', installments: 2 })
  })
})
