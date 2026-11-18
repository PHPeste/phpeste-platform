import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Nome Sobrenome', email: 'n@s.dev', avatarUrl: null }, token: 'e2e-token' }
const base = {
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHPeste', slug: 'phpeste' },
  status: 'cfp_open', status_label: 'CFP aberto', allowed_transitions: ['sales_open'],
  status_options: [{ status: 'sales_open', label: 'Vendas abertas', unlocks: ['Venda de ingressos, quando houver lotes configurados'], blocks: ['Não dá para voltar para CFP aberto nem para rascunho'] }],
  starts_on: '2027-10-08', ends_on: '2027-10-09', year: 2027, city: 'João Pessoa', state: 'PB', timezone: 'America/Fortaleza',
  site_mode: 'platform', external_site_url: null, site_url: null, privacy_contact_email: null, privacy_policy_url: null, anonymize_after_event: false, role: 'organizador',
}

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, handler: (route: Route, method: string, path: string) => Promise<void> | void) {
  let edition = { ...base }
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition] } }))
  await page.route('**/api/v1/editions/ed-27**', async (route) => {
    const method = route.request().method()
    const path = new URL(route.request().url()).pathname.replace('/api/v1/editions/ed-27', '') || '/'
    if (path === '/overview') return route.abort()
    const custom = await handler(route, method, path)
    if (custom !== undefined) return
    if (path === '/' && method === 'GET') return route.fulfill({ status: 200, json: { data: edition } })
    if (path === '/' && method === 'PATCH') {
      edition = { ...edition, ...route.request().postDataJSON() }
      return route.fulfill({ status: 200, json: { data: edition } })
    }
    if (path === '/status') {
      edition = { ...edition, status: 'sales_open', status_label: 'Vendas abertas', status_options: [] }
      return route.fulfill({ status: 200, json: { data: edition } })
    }
    if (path === '/domains') return route.fulfill({ status: 200, json: { data: [] } })
    if (path.startsWith('/audit')) return route.fulfill({ status: 200, json: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } } })
    return route.fulfill({ status: 404, json: {} })
  })
  await goto('/configuracoes', { waitUntil: 'hydration' })
  await expect(page.getByRole('heading', { name: 'Configurações', level: 1 })).toBeVisible()
}

test.describe('Configurações da edição', () => {
  test('salva dados de várias abas num clique e o nome novo aparece na barra superior', async ({ page, goto }) => {
    const bodies: unknown[] = []
    await open(page, goto, (route, method, path) => {
      if (path === '/' && method === 'PATCH') bodies.push(route.request().postDataJSON())
    })

    await expect(page.getByLabel('Identificador na URL')).toBeDisabled()
    await page.getByRole('textbox', { name: /^Nome/ }).fill('PHPeste 2027 · João Pessoa')
    await page.getByRole('tab', { name: 'Privacidade (LGPD)' }).click()
    await page.getByLabel('E-mail do encarregado de dados').fill('dpo@php-pb.net')
    await page.getByLabel('Anonimizar os dados dos participantes depois do evento').check()
    await expect(page.getByRole('tab', { name: 'Edição •' })).toBeVisible()

    await page.getByRole('button', { name: 'Salvar alterações' }).click()
    await expect(page.getByText('Configurações salvas.')).toBeVisible()
    expect(bodies).toEqual([{
      name: 'PHPeste 2027 · João Pessoa', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', timezone: 'America/Fortaleza',
      privacy_contact_email: 'dpo@php-pb.net', privacy_policy_url: null, anonymize_after_event: true,
    }])
    await expect(page.getByRole('button', { name: 'Trocar de edição' })).toContainText('PHPeste 2027 · João Pessoa')
  })

  test('mudar a etapa mostra o que libera e bloqueia antes de confirmar', async ({ page, goto }) => {
    const calls: string[] = []
    await open(page, goto, (_route, method, path) => {
      if (path === '/status') calls.push(method)
    })

    const card = page.locator('[data-status="sales_open"]')
    await expect(card).toContainText('Venda de ingressos, quando houver lotes configurados')
    await expect(card).toContainText('Não dá para voltar para CFP aberto nem para rascunho')
    await card.getByRole('button', { name: 'Passar para Vendas abertas' }).click()

    const dialog = page.getByRole('dialog', { name: 'Passar a edição para "Vendas abertas"?' })
    await expect(dialog).toContainText('Bloqueia')
    expect(calls).toEqual([])
    await dialog.getByRole('button', { name: 'Passar para Vendas abertas' }).click()

    await expect(page.getByText('A edição passou para "Vendas abertas".')).toBeVisible()
    await expect(page.locator('[data-status="sales_open"]')).toHaveCount(0)
    expect(calls).toEqual(['POST'])
  })

  test('domínio com instruções de DNS e site próprio com o endereço da comunidade', async ({ page, goto }) => {
    let domains: unknown[] = []
    await open(page, goto, async (route, method, path) => {
      if (path === '/domains' && method === 'POST') {
        domains = [{ id: 'd-1', host: route.request().postDataJSON().host, is_platform: false, is_primary: true, verified: false, verified_at: null, verification: { txt: { name: '_phpeste-verify.cfp.phpeste.php-pb.net', value: 'phpeste-verify=abc' }, cname: { name: 'cfp.phpeste.php-pb.net', value: 'edicoes.phpeste.dev' } }, last_checked_at: null, last_check_error: null, url: 'https://cfp.phpeste.php-pb.net' }]
        await route.fulfill({ status: 201, json: { data: domains[0] } })
        return true as never
      }
      if (path === '/domains' && method === 'GET') {
        await route.fulfill({ status: 200, json: { data: domains } })
        return true as never
      }
      if (path === '/domains/d-1/verify') {
        domains = [{ ...(domains[0] as object), verified: true, verification: null }]
        await route.fulfill({ status: 200, json: { data: domains[0] } })
        return true as never
      }
    })

    await page.getByRole('tab', { name: 'Domínio e site' }).click()
    await page.getByLabel('Novo domínio').fill('CFP.phpeste.php-pb.net')
    await page.getByRole('button', { name: 'Adicionar' }).click()
    const domain = page.locator('[data-host="cfp.phpeste.php-pb.net"]')
    await expect(domain).toContainText('Aguardando DNS')
    await expect(domain).toContainText('edicoes.phpeste.dev')
    await domain.getByRole('button', { name: 'Verificar' }).click()
    await expect(page.getByText('cfp.phpeste.php-pb.net verificado.')).toBeVisible()

    await page.getByLabel('Site próprio da comunidade').check()
    await page.getByLabel('Endereço do site da comunidade').fill('https://phpeste.php-pb.net')
    const guide = page.getByTestId('site-guide')
    await expect(guide).toContainText('https://cfp.phpeste.php-pb.net/cfp')
    await expect(guide).toContainText('https://cfp.phpeste.php-pb.net/palestrante/propostas')
    await expect(guide).toContainText('ed-27')
    await page.getByRole('button', { name: 'Salvar alterações' }).click()
    await expect(page.getByText('Configurações salvas.')).toBeVisible()
  })

  test('auditoria com ação legível, pessoa, detalhe e filtros', async ({ page, goto }) => {
    const queries: string[] = []
    await open(page, goto, async (route, _method, path) => {
      if (!path.startsWith('/audit')) return
      queries.push(new URL(route.request().url()).search)
      await route.fulfill({ status: 200, json: {
        data: [{ id: 'a-1', action: 'edition.status_changed', subject_type: 'edition', subject_id: 'ed-27', actor: { id: 'u-1', name: 'Mariana Sales' }, before: { status: 'cfp_open' }, after: { status: 'sales_open' }, ip: null, request_id: null, created_at: '2027-03-10T15:50:00Z' }],
        meta: { current_page: 1, last_page: 1, total: 1 },
      } })
      return true as never
    })

    await page.getByRole('tab', { name: 'Auditoria' }).click()
    await expect(page.getByRole('row', { name: /Mariana Sales.*Mudou a etapa da edição.*etapa: CFP aberto → Vendas abertas/ })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Salvar alterações' })).toHaveCount(0)

    await page.getByLabel('Ação').selectOption('team.invited')
    await page.getByLabel('Pessoa').selectOption('u-1')
    await expect.poll(() => queries.at(-1)).toBe('?page=1&action=team.invited&actor_id=u-1')
  })

  test('avisa ao sair com alterações não salvas', async ({ page, goto }) => {
    await open(page, goto, () => {})
    await page.getByLabel('Cidade').fill('Campina Grande')
    await page.getByRole('navigation', { name: 'Principal' }).getByRole('link', { name: 'Visão geral' }).click()
    await page.getByRole('dialog', { name: 'Sair sem salvar?' }).getByRole('button', { name: 'Continuar editando' }).click()
    await expect(page.getByLabel('Cidade')).toHaveValue('Campina Grande')
  })
})
