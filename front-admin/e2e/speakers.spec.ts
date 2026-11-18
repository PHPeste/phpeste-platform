import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Nome Sobrenome', email: 'n@s.dev', avatarUrl: null }, token: 'e2e-token' }
const edition = {
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHPeste', slug: 'phpeste' },
  status: 'sales_open', status_label: 'Vendas abertas', starts_on: null, ends_on: null, city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role: 'organizador',
}
const NE = ['AL', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'SE']
const row = (number: number, name: string, state: string, status: string, label: string) => ({
  user_id: `u-${number}`, name, email: `${name.split(' ')[0]!.toLowerCase()}@comunidade.dev`, state, nordeste: NE.includes(state),
  proposal: { id: `p-${number}`, number, title: ['Octane em produção', 'PHP no celular', 'Filas e idempotência'][number - 1], track: 'Backend e arquitetura', origin: 'cfp' },
  status, status_label: label, deadline_at: '2027-05-08T02:59:59Z', reminded_at: null, attended_at: null,
})
const detail = (canViewPrivate: boolean, attended: string | null = null) => ({ data: {
  user_id: 'u-1', name: 'Ana Martins', email: 'ana@comunidade.dev', can_view_private_data: canViewPrivate,
  profile: { display_name: 'Ana Martins', job_title: 'Engenheira de software', company: null, bio: 'Trabalha com PHP há dez anos.', city: 'João Pessoa', state: 'PB', links: { website: null, github: 'https://github.com/ana', linkedin: null }, photo_url: null },
  proposals: [{ id: 'p-1', number: 1, title: 'Octane em produção', track: 'Backend e arquitetura', origin: 'cfp', origin_label: 'Pelo CFP', format_label: 'Palestra de 30 min', duration_minutes: 30, decision: 'accepted', decision_label: 'Aprovada', status: 'confirmed', status_label: 'Confirmada', deadline_at: '2027-05-08T02:59:59Z', responded_at: '2027-05-02T13:00:00Z', reminded_at: null, attended_at: attended, reason: null }],
  logistics: { user_id: 'u-1', origin_city: 'João Pessoa', origin_state: 'PB', needs_travel: false, needs_lodging: true, travel_paid_by_organization: false, lodging_paid_by_organization: false, organization_notes: null, restrictions: canViewPrivate ? { accessibility_needs: 'Rampa no palco', dietary_restrictions: 'Vegetariana' } : null },
} })

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, url: string, handler: (route: Route, method: string, path: string) => Promise<void> | void) {
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition] } }))
  await page.route('**/api/v1/editions/ed-27/overview', (route) => route.abort())
  await page.route('**/api/v1/editions/ed-27/cfp/**', (route) => handler(route, route.request().method(), new URL(route.request().url()).pathname.replace('/api/v1/editions/ed-27/cfp', '')))
  await goto(url, { waitUntil: 'hydration' })
}

test.describe('Palestrantes', () => {
  test('mostra indicadores, cobertura dos nove estados, filtros, lembrete e promoção com confirmação', async ({ page, goto }) => {
    const calls: string[] = []
    let promoted = false
    await open(page, goto, '/palestrantes', async (route, method, path) => {
      if (path === '/speakers') {
        return route.fulfill({ status: 200, json: {
          summary: { confirmed: 1, awaiting: 1, waitlisted: promoted ? 0 : 1, slots: 24, nordeste_percent: 100, nordeste_quota_percent: 50 },
          coverage: NE.map((state) => ({ state, confirmed: state === 'PB' ? 1 : 0 })),
          data: [row(1, 'Ana Martins', 'PB', 'confirmed', 'Confirmada'), row(2, 'Joana Souza', 'BA', 'pending', 'Aguardando resposta'), row(3, 'Rafael Freitas', 'SP', promoted ? 'pending' : 'waitlisted', promoted ? 'Aguardando resposta' : 'Lista de espera')],
        } })
      }
      if (path === '/promotions' && method === 'GET') {
        return route.fulfill({ status: 200, json: { data: promoted ? [] : [{ track: { id: 't-1', name: 'Backend e arquitetura', slots: 2 }, occupied: 1, open_slots: 1, candidates: [{ proposal_id: 'p-3', number: 3, title: 'Filas e idempotência', final_score: 3.9 }] }] } })
      }
      calls.push(`${method} ${path} ${route.request().postData() ?? ''}`)
      if (path === '/promotions') {
        promoted = true
        return route.fulfill({ status: 200, json: { data: { proposal_id: 'p-3', decision: 'accepted', confirmation_status: 'pending' } } })
      }
      if (path === '/speakers/remind') return route.fulfill({ status: 200, json: { data: { reminded: 1, skipped: 0 } } })
      return route.fulfill({ status: 404, json: {} })
    })

    await expect(page.getByRole('heading', { name: 'Palestrantes', level: 1 })).toBeVisible()
    const kpis = page.getByRole('region', { name: 'Resumo dos palestrantes' })
    await expect(kpis).toContainText('meta de 24')
    await expect(kpis).toContainText('100%')
    await expect(page.locator('[data-state="AL"]')).toContainText('sem representante')
    await expect(page.locator('[data-state="PB"]')).toContainText('1')
    await expect(page.getByText('8 estados sem representante: AL, BA, CE, MA, PE, PI, RN, SE.')).toBeVisible()

    await page.getByRole('button', { name: /Lista de espera 1/ }).click()
    await expect(page.getByRole('table', { name: 'Palestrantes da edição' }).locator('tbody tr')).toHaveCount(1)
    await expect(page.getByRole('row', { name: /Rafael Freitas.*SP.*Filas e idempotência.*Lista de espera/ })).toBeVisible()
    await page.getByRole('button', { name: /Todos 3/ }).click()
    await page.getByRole('searchbox', { name: 'Buscar por nome, e-mail ou palestra' }).fill('celular')
    await expect(page.getByRole('table', { name: 'Palestrantes da edição' }).locator('tbody tr')).toHaveCount(1)
    await page.getByRole('searchbox', { name: 'Buscar por nome, e-mail ou palestra' }).fill('')

    await page.getByRole('button', { name: 'Enviar lembrete' }).click()
    const remind = page.getByRole('dialog', { name: 'Lembrar quem está aguardando?' })
    await expect(remind).toContainText('para a pessoa que ainda não respondeu')
    await remind.getByRole('button', { name: 'Enviar' }).click()
    await expect(page.getByText('1 lembrete enviado.')).toBeVisible()

    await expect(page.getByText('Backend e arquitetura: 1 vaga aberta')).toBeVisible()
    await page.getByRole('button', { name: 'Promover #3' }).click()
    const dialog = page.getByRole('dialog', { name: 'Promover da lista de espera?' })
    await expect(dialog).toContainText('A proposta #3 "Filas e idempotência" passa a aprovada')
    expect(calls.filter((call) => call.startsWith('POST /promotions'))).toEqual([])
    await dialog.getByRole('button', { name: 'Promover' }).click()
    await expect(page.getByText('Proposta #3 promovida. O convite de confirmação foi enviado.')).toBeVisible()
    await expect(page.getByRole('row', { name: /Rafael Freitas.*Aguardando resposta/ })).toBeVisible()
    expect(calls).toEqual(['POST /speakers/remind {}', 'POST /promotions {"proposal_id":"p-3"}'])
  })

  test('detalhe: perfil, palestras, logística com acessibilidade e presença', async ({ page, goto }) => {
    let attended: string | null = null
    let saved: unknown = null
    await open(page, goto, '/palestrantes/u-1', async (route, method, path) => {
      if (path === '/speakers/u-1') return route.fulfill({ status: 200, json: detail(true, attended) })
      if (path === '/speakers/u-1/attendance') {
        attended = route.request().postDataJSON().present ? '2027-10-08T12:00:00Z' : null
        return route.fulfill({ status: 200, json: { data: { updated: 1 } } })
      }
      if (path === '/logistics/u-1') {
        saved = route.request().postDataJSON()
        return route.fulfill({ status: 200, json: { data: detail(true).data.logistics } })
      }
      return route.fulfill({ status: 404, json: {} })
    })

    await expect(page.getByRole('heading', { name: 'Ana Martins', level: 1 })).toBeVisible()
    await expect(page.getByText('Engenheira de software · João Pessoa · PB')).toBeVisible()
    await expect(page.getByText('Trabalha com PHP há dez anos.')).toBeVisible()
    await expect(page.getByRole('link', { name: 'GitHub' })).toHaveAttribute('href', 'https://github.com/ana')

    await page.getByRole('tab', { name: 'Palestras' }).click()
    await expect(page.getByText('#1 · Backend e arquitetura · 30 min')).toBeVisible()

    await page.getByRole('tab', { name: 'Viagem e hospedagem' }).click()
    await expect(page.getByTestId('private-data')).toContainText('Rampa no palco')
    await page.getByLabel('Hotel pago pela organização').check()
    await page.getByLabel('Anotações da organização').fill('Reservar quarto acessível.')
    await page.getByRole('button', { name: 'Salvar alterações' }).click()
    await expect(page.getByText('Viagem e hospedagem salvas.')).toBeVisible()
    expect(saved).toEqual({ travel_paid_by_organization: false, lodging_paid_by_organization: true, organization_notes: 'Reservar quarto acessível.' })

    await page.getByRole('button', { name: 'Marcar como presente' }).click()
    await expect(page.getByText('Ana Martins marcada como presente.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Desfazer presença' })).toBeVisible()
  })

  test('sem permissão, acessibilidade e alimentação não aparecem', async ({ page, goto }) => {
    await open(page, goto, '/palestrantes/u-1', (route, _method, path) => (path === '/speakers/u-1' ? route.fulfill({ status: 200, json: detail(false) }) : route.fulfill({ status: 404, json: {} })))

    await page.getByRole('tab', { name: 'Viagem e hospedagem' }).click()
    await expect(page.getByLabel('Hotel pago pela organização')).toBeVisible()
    await expect(page.getByTestId('private-data')).toHaveCount(0)
    await expect(page.getByRole('main')).not.toContainText('Rampa')
  })

  test('convida palestrante direto e a palestra aparece como convidada', async ({ page, goto }) => {
    let invited: Record<string, unknown> | null = null
    await open(page, goto, '/palestrantes', async (route, method, path) => {
      if (path === '/speakers') {
        const rows = invited ? [{ ...row(9, 'Keynote Convidada', 'PE', 'pending', 'Aguardando resposta'), proposal: { id: 'p-9', number: 9, title: 'O futuro do PHP', track: 'Backend e arquitetura', origin: 'invited' } }] : []
        return route.fulfill({ status: 200, json: { summary: { confirmed: 0, awaiting: rows.length, waitlisted: 0, slots: 6, nordeste_percent: null, nordeste_quota_percent: 50 }, coverage: NE.map((state) => ({ state, confirmed: 0 })), data: rows } })
      }
      if (path === '/promotions') return route.fulfill({ status: 200, json: { data: [] } })
      if (path === '/tracks') return route.fulfill({ status: 200, json: { data: [{ id: 't-1', name: 'Backend e arquitetura' }, { id: 't-2', name: 'Carreira e comunidade' }] } })
      if (path === '/settings') return route.fulfill({ status: 200, json: { data: {}, meta: { form: { formats: [{ id: 'talk', label: 'Palestra de 30 min' }, { id: 'workshop', label: 'Oficina de 2 h' }] } } } })
      if (path === '/speakers/invite' && method === 'POST') {
        invited = route.request().postDataJSON()
        if ((invited as { email: string }).email === 'ja@comunidade.dev') {
          invited = null
          return route.fulfill({ status: 422, json: { code: 'validation_error', message: 'x', errors: { email: ['Essa pessoa já tem palestra aprovada nesta edição.'] }, request_id: 'r' } })
        }
        return route.fulfill({ status: 201, json: { data: { proposal_id: 'p-9', number: 9, user_id: 'u-9', deadline_at: '2027-05-11T23:59:59Z' } } })
      }
      return route.fulfill({ status: 404, json: {} })
    })

    await page.getByRole('button', { name: 'Convidar palestrante' }).click()
    const dialog = page.getByRole('dialog', { name: 'Convidar palestrante' })
    await dialog.getByRole('textbox', { name: /^Nome/ }).fill('Keynote Convidada')
    await dialog.getByRole('textbox', { name: /^E-mail/ }).fill('ja@comunidade.dev')
    await dialog.getByRole('textbox', { name: /^Título da palestra/ }).fill('O futuro do PHP')
    await dialog.getByLabel('Trilha').selectOption({ label: 'Carreira e comunidade' })
    await dialog.getByRole('button', { name: 'Enviar convite' }).click()
    await expect(dialog.getByText('Essa pessoa já tem palestra aprovada nesta edição.')).toBeVisible()

    await dialog.getByRole('textbox', { name: /^E-mail/ }).fill('keynote@comunidade.dev')
    await dialog.getByRole('button', { name: 'Enviar convite' }).click()
    await expect(page.getByText('Convite enviado. A palestra #9 aguarda a confirmação.')).toBeVisible()
    expect(invited).toEqual({ name: 'Keynote Convidada', email: 'keynote@comunidade.dev', title: 'O futuro do PHP', track_id: 't-2', format: 'talk', summary: null })
    await expect(page.getByRole('row', { name: /Keynote Convidada.*O futuro do PHP.*Convidada.*Aguardando resposta/ })).toBeVisible()
  })
})
