import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page } from '@playwright/test'

const money = (amount: number) => ({ amount, currency: 'BRL' })
const base = '**/api/v1/attendee/editions/ed-a'

const ticket = (overrides: Record<string, unknown> = {}) => ({
  id: 'tk-1', code: 'K7QM-4XTD', status: 'active', status_label: 'Válido', order_id: 'o-1', order_number: 42, ticket_type_name: 'Inteira', batch_name: '1º lote',
  holder: { name: 'Ada Lovelace', email: 'ada@x.dev', badge_name: null, company: null, tshirt_size: null, accessibility: null },
  missing: ['badge_name', 'tshirt_size'], issued_at: '2027-06-10T12:00:00Z', cancelled_at: null, qr: 'PHT1.tk-1.1.abc', transfers: [],
  ...overrides,
})

interface State { tickets: Array<ReturnType<typeof ticket>>, verified: boolean, calls: Array<{ method: string, path: string, body: unknown }> }

async function mockApi(page: Page, state: State) {
  await page.route('**/api/v1/auth/login', (route) => route.fulfill({ json: { user: { id: 'u-1', name: 'Ada Lovelace', email: 'ada@x.dev', avatar_url: null, created_at: '2027-01-01' }, token: 'tok' } }))
  await page.route(`${base}/tickets**`, async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname.replace('/api/v1/attendee/editions/ed-a/tickets', '')
    state.calls.push({ method: request.method(), path, body: request.postDataJSON?.() ?? null })
    if (path === '') return route.fulfill({ json: { data: state.tickets, meta: { email_verified: state.verified } } })
    const current = state.tickets.find((item) => path.startsWith(`/${item.id}`))
    if (!current) return route.fulfill({ status: 404, json: { message: 'Not found' } })
    if (path.endsWith('/transfer')) {
      const body = request.postDataJSON() as { name: string, email: string }
      state.tickets = state.tickets.filter((item) => item.id !== current.id)
      return route.fulfill({ json: { data: { ...current, holder: { ...current.holder, name: body.name, email: body.email }, qr: undefined } } })
    }
    if (request.method() === 'PATCH') {
      const body = request.postDataJSON() as Record<string, string | null>
      if (body.badge_name === 'erro') return route.fulfill({ status: 422, json: { message: 'invalid', errors: { badge_name: ['Nome do crachá inválido.'] } } })
      Object.assign(current.holder, { name: body.holder_name, badge_name: body.badge_name, company: body.company, tshirt_size: body.tshirt_size, accessibility: body.accessibility })
      current.missing = [!body.badge_name && 'badge_name', !body.tshirt_size && 'tshirt_size'].filter(Boolean) as string[]
      return route.fulfill({ json: { data: current } })
    }
    return route.fulfill({ json: { data: current } })
  })
  await page.route(`${base}/orders`, (route) => route.fulfill({ json: { meta: { email_verified: state.verified }, data: [{
    id: 'o-1', number: 42, status: 'paid', status_label: 'Pago', subtotal: money(30000), discount: money(3000), fee: money(1080), total: money(28080), refunded: money(14040),
    coupon_code: 'SERTAO10', created_at: '2027-06-10T12:00:00Z', paid_at: '2027-06-10T12:05:00Z', expires_at: null,
    items: [
      { id: 'i-1', ticket_type_name: 'Inteira', batch_name: '1º lote', attendee_name: 'Ada Lovelace', total: money(14040), cancelled_at: null },
      { id: 'i-2', ticket_type_name: 'Inteira', batch_name: '1º lote', attendee_name: 'Grace Hopper', total: money(14040), cancelled_at: '2027-06-11T10:00:00Z' },
    ],
    refunds: [{ id: 'r-1', amount: money(14040), status: 'completed', status_label: 'Concluído', reason_label: 'Pedido de quem comprou', created_at: '2027-06-11T10:00:00Z', completed_at: '2027-06-11T10:01:00Z' }],
  }] } }))
  await page.route('**/api/v1/auth/email/verification-notification', (route) => {
    state.calls.push({ method: 'POST', path: 'verification', body: null })
    return route.fulfill({ json: { sent: true } })
  })
}

const signedIn = (page: Page) => page.addInitScript(() => localStorage.setItem('app.auth', JSON.stringify({ user: { id: 'u-1', name: 'Ada Lovelace', email: 'ada@x.dev', avatarUrl: null }, token: 'tok' })))

test.describe('conta do participante', () => {
  test('entra pela conta e vê os ingressos com QR, o que falta no crachá e salva a imagem', async ({ page, goto }) => {
    const state: State = { tickets: [ticket()], verified: true, calls: [] }
    await mockApi(page, state)
    await goto('/conta', { waitUntil: 'hydration' })

    await expect(page).toHaveURL(/\/conta\/ingressos$/)
    const auth = page.getByTestId('auth-panel')
    await expect(auth).toContainText('Entre para ver seus ingressos')
    await auth.getByLabel(/^E-mail/).fill('ada@x.dev')
    await auth.getByLabel(/^Senha/).fill('uma-senha-forte')
    await auth.getByRole('button', { name: 'Entrar' }).click()

    const card = page.getByTestId('account-ticket')
    await expect(card.getByRole('img', { name: 'QR code do ingresso de Ada Lovelace' })).toBeVisible()
    await expect(card).toContainText('Código K7QM-4XTD')
    await expect(card).toContainText('Faltam dados do crachá: nome no crachá, camiseta')
    await expect(page.getByRole('navigation', { name: 'Seções da conta' }).getByRole('link', { name: 'Ingressos' })).toHaveAttribute('aria-current', 'page')

    const download = page.waitForEvent('download')
    await card.getByRole('button', { name: 'Salvar imagem: Ada Lovelace' }).click()
    expect((await download).suggestedFilename()).toBe('ingresso-K7QM-4XTD.png')
  })

  test('edita os dados do crachá, com erro de campo do backend no campo certo', async ({ page, goto }) => {
    const state: State = { tickets: [ticket()], verified: true, calls: [] }
    await mockApi(page, state)
    await signedIn(page)
    await goto('/conta/ingressos/tk-1', { waitUntil: 'hydration' })

    await expect(page.getByTestId('ticket-detail').getByRole('img', { name: 'QR code do ingresso de Ada Lovelace' })).toBeVisible()
    await page.getByLabel('Nome no crachá').fill('erro')
    await page.getByRole('button', { name: 'Salvar dados' }).click()
    await expect(page.getByLabel('Nome no crachá')).toHaveAttribute('aria-invalid', 'true')
    await expect(page.getByText('Nome do crachá inválido.').first()).toBeVisible()

    await page.getByLabel('Nome no crachá').fill('Ada')
    await page.getByLabel('Empresa ou comunidade').fill('PHP-PB')
    await page.getByLabel('Camiseta').selectOption('BL-M')
    await page.getByLabel('Acessibilidade').fill('Intérprete de Libras')
    await page.getByRole('button', { name: 'Salvar dados' }).click()

    await expect(page.getByRole('status').filter({ hasText: 'Dados do crachá salvos.' })).toBeVisible()
    expect(state.calls.filter((call) => call.method === 'PATCH').at(-1)?.body).toEqual({ holder_name: 'Ada Lovelace', badge_name: 'Ada', company: 'PHP-PB', tshirt_size: 'BL-M', accessibility: 'Intérprete de Libras' })

    await page.getByRole('link', { name: 'Voltar para meus ingressos' }).click()
    await expect(page.getByTestId('account-ticket')).not.toContainText('Faltam dados do crachá')
  })

  test('transferir pede confirmação e tira o QR da tela e do aparelho na hora', async ({ page, goto }) => {
    const state: State = { tickets: [ticket()], verified: true, calls: [] }
    await mockApi(page, state)
    await signedIn(page)
    await goto('/conta/ingressos/tk-1', { waitUntil: 'hydration' })
    await expect(page.getByTestId('ticket-detail')).toBeVisible()

    await page.getByLabel(/^E-mail de quem vai receber/).fill('ada@x.dev')
    await page.getByLabel(/^Nome de quem vai receber/).fill('Bia Lima')
    await page.getByRole('button', { name: 'Transferir ingresso' }).click()
    await expect(page.getByText('O ingresso já está com este e-mail.')).toBeVisible()

    await page.getByLabel(/^E-mail de quem vai receber/).fill('bia@x.dev')
    await page.getByRole('button', { name: 'Transferir ingresso' }).click()
    const dialog = page.getByRole('dialog', { name: 'Transferir o ingresso?' })
    await expect(dialog).toContainText('O ingresso vai para Bia Lima (bia@x.dev)')
    await dialog.getByRole('button', { name: 'Sim, transferir' }).click()

    await expect(page.getByTestId('ticket-transferred')).toContainText('O QR code anterior não vale mais.')
    await expect(page.getByTestId('ticket-qr')).toHaveCount(0)
    expect(state.calls.filter((call) => call.path.endsWith('/transfer'))).toHaveLength(1)
    const saved = await page.evaluate(() => localStorage.getItem('phpeste.account.tickets.ed-a.u-1'))
    expect(saved).not.toContain('PHT1.tk-1')

    await page.getByRole('link', { name: 'Voltar para meus ingressos' }).click()
    await expect(page.getByText('Nenhum ingresso nesta conta.')).toBeVisible()
  })

  test('depois de carregado, o QR continua aparecendo sem internet, mesmo recarregando a página', async ({ page, goto, context }) => {
    const state: State = { tickets: [ticket()], verified: true, calls: [] }
    await mockApi(page, state)
    await signedIn(page)
    await goto('/conta/ingressos', { waitUntil: 'hydration' })
    await expect(page.getByTestId('account-ticket').getByTestId('ticket-qr')).toBeVisible()
    await page.waitForFunction(async () => {
      const cache = await caches.open('phpeste-ingressos-v1')
      return !!(await cache.match('/conta/ingressos'))
    })

    await page.unroute(`${base}/tickets**`)
    await page.route(`${base}/tickets**`, (route) => route.abort('internetdisconnected'))
    await context.setOffline(true)
    await page.reload()

    await expect(page.getByTestId('offline-notice')).toContainText('Sem conexão. Mostrando os ingressos salvos neste aparelho')
    await expect(page.getByTestId('account-ticket').getByRole('img', { name: 'QR code do ingresso de Ada Lovelace' })).toBeVisible()
    await context.setOffline(false)
  })

  test('pedidos com itens, cupom e reembolso; e-mail sem confirmação avisa e reenvia', async ({ page, goto }) => {
    const state: State = { tickets: [], verified: false, calls: [] }
    await mockApi(page, state)
    await signedIn(page)
    await goto('/conta/pedidos', { waitUntil: 'hydration' })

    const order = page.getByTestId('account-order')
    await expect(order.getByRole('heading', { name: 'Pedido #42' })).toBeVisible()
    await expect(order).toContainText('Grace Hopper · Inteira (1º lote)cancelado')
    await expect(order).toContainText('Desconto (SERTAO10)')
    await expect(order).toContainText(/Total\s*R\$\s280,80/)
    await expect(order.getByRole('heading', { name: 'Reembolsos' })).toBeVisible()
    await expect(order).toContainText('Pedido de quem comprou')

    const notice = page.getByTestId('verify-email')
    await expect(notice).toContainText('Ingressos e pedidos feitos com ada@x.dev só aparecem aqui depois que você confirma o e-mail.')
    await notice.getByRole('button', { name: 'Reenviar e-mail de confirmação' }).click()
    await expect(notice.getByRole('status')).toContainText('Enviamos de novo.')
    expect(state.calls.some((call) => call.path === 'verification')).toBe(true)
  })
})
