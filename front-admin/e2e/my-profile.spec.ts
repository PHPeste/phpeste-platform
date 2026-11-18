import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Nome Sobrenome', email: 'n@s.dev', avatarUrl: null }, token: 'e2e-token' }
const edition = {
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHPeste', slug: 'phpeste' },
  status: 'cfp_open', status_label: 'CFP aberto', starts_on: null, ends_on: null, city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role: 'organizador',
}
const user = (overrides: Record<string, unknown> = {}) => ({
  id: 'u-me', name: 'Nome Sobrenome', email: 'n@s.dev', avatar_url: null, locale: 'pt_BR', has_password: true, providers: [],
  notifications: [
    { id: 'proposals_to_review', label: 'Novas propostas para avaliar', description: 'Quando a organização atribui propostas para você revisar.', enabled: true },
    { id: 'weekly_summary', label: 'Resumo semanal da edição', description: 'Toda segunda.', enabled: false },
  ],
  ...overrides,
})

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, calls: string[]) {
  let me = user()
  let sessions = [{ id: '1', name: 'Painel PHPeste', current: true, last_used_at: null, created_at: null }, { id: '2', name: 'App PHPeste · Android', current: false, last_used_at: '2027-03-09T12:00:00Z', created_at: null }]
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition] } }))
  await page.route('**/api/v1/editions/ed-27/overview', (route) => route.abort())
  await page.route('**/api/v1/me**', async (route) => {
    const method = route.request().method()
    const path = new URL(route.request().url()).pathname.replace('/api/v1/me', '') || '/'
    calls.push(`${method} ${path} ${route.request().postData() ?? ''}`.trim())
    if (path === '/sessions') return route.fulfill({ status: 200, json: { data: sessions } })
    if (path.startsWith('/sessions/')) {
      sessions = sessions.filter((item) => item.id !== path.split('/').pop())
      return route.fulfill({ status: 204 })
    }
    if (method === 'PATCH') {
      const body = route.request().postDataJSON()
      me = { ...me, ...(body.name ? { name: body.name } : {}), notifications: me.notifications.map((item) => (body.notifications && item.id in body.notifications ? { ...item, enabled: body.notifications[item.id] } : item)) }
      return route.fulfill({ status: 200, json: { data: me } })
    }
    return route.fulfill({ status: 200, json: { user: me, roles: [] } })
  })
  await page.route('**/api/v1/auth/password', (route) => {
    calls.push(`PUT /auth/password ${route.request().postData()}`)
    const body = route.request().postDataJSON()
    if (body.current_password !== 'senha-atual') return route.fulfill({ status: 422, json: { code: 'validation_error', message: 'x', errors: { current_password: ['A senha atual está incorreta.'] }, request_id: 'r' } })
    return route.fulfill({ status: 200, json: { updated: true } })
  })
  await goto('/', { waitUntil: 'hydration' })
  await page.getByRole('button', { name: 'Menu do usuário' }).click()
  await page.getByRole('link', { name: 'Meu perfil' }).click()
  await expect(page.getByRole('heading', { name: 'Meu perfil', level: 1 })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Meu perfil' })).toBeHidden()
}

test.describe('Meu perfil', () => {
  test('troca o nome pelo menu do usuário e o cabeçalho acompanha', async ({ page, goto }) => {
    const calls: string[] = []
    await open(page, goto, calls)

    await expect(page.getByLabel('E-mail', { exact: true })).toBeDisabled()
    await page.getByRole('textbox', { name: /^Nome/ }).fill('Nome Sobrenome Lima')
    await page.getByRole('button', { name: 'Salvar' }).click()
    await expect(page.getByText('Perfil salvo.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Menu do usuário' })).toContainText('Nome Sobrenome Lima')
    expect(calls).toContain('PATCH / {"name":"Nome Sobrenome Lima","locale":"pt_BR"}')
  })

  test('senha: erro da senha atual no campo e troca com sucesso', async ({ page, goto }) => {
    const calls: string[] = []
    await open(page, goto, calls)
    await page.getByRole('tab', { name: 'Senha e segurança' }).click()

    await page.getByLabel('Senha atual').fill('errada')
    await page.getByLabel('Nova senha', { exact: true }).fill('uma-senha-nova-forte')
    await page.getByLabel('Repetir nova senha').fill('uma-senha-nova-forte')
    await page.getByRole('button', { name: 'Alterar senha' }).click()
    await expect(page.getByText('A senha atual está incorreta.')).toBeVisible()

    await page.getByLabel('Senha atual').fill('senha-atual')
    await page.getByRole('button', { name: 'Alterar senha' }).click()
    await expect(page.getByText('Senha alterada. As outras sessões foram encerradas.')).toBeVisible()
    await expect(page.getByLabel('Senha atual')).toHaveValue('')
  })

  test('encerrar a sessão de outro dispositivo pede confirmação e revoga', async ({ page, goto }) => {
    const calls: string[] = []
    await open(page, goto, calls)
    await page.getByRole('tab', { name: 'Senha e segurança' }).click()

    await expect(page.locator('[data-session="1"]')).toContainText('Este dispositivo')
    await expect(page.locator('[data-session="1"]').getByRole('button')).toHaveCount(0)
    await page.getByRole('button', { name: 'Encerrar sessão App PHPeste · Android' }).click()
    const dialog = page.getByRole('dialog', { name: 'Encerrar a sessão "App PHPeste · Android"?' })
    expect(calls.filter((call) => call.startsWith('DELETE'))).toEqual([])
    await dialog.getByRole('button', { name: 'Encerrar' }).click()

    await expect(page.getByText('Sessão "App PHPeste · Android" encerrada.')).toBeVisible()
    await expect(page.locator('[data-session="2"]')).toHaveCount(0)
    expect(calls).toContain('DELETE /sessions/2')
  })

  test('liga e desliga notificações por e-mail', async ({ page, goto }) => {
    const calls: string[] = []
    await open(page, goto, calls)
    await page.getByRole('tab', { name: 'Notificações' }).click()

    await expect(page.getByLabel(/Novas propostas para avaliar/)).toBeChecked()
    await page.getByLabel(/Resumo semanal da edição/).check()
    await expect(page.getByText('Resumo semanal da edição ligado.')).toBeVisible()
    expect(calls).toContain('PATCH / {"notifications":{"weekly_summary":true}}')
  })
})
