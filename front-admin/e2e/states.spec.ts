import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Nome Sobrenome', email: 'n@s.dev', avatarUrl: null }, token: 'e2e-token' }
const edition = {
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHPeste', slug: 'phpeste' },
  status: 'cfp_open', status_label: 'CFP aberto', starts_on: null, ends_on: null, city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role: 'organizador',
}

async function signIn(page: Page) {
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition] } }))
  await page.route('**/api/v1/editions/ed-27/overview', (route) => route.abort())
}

test.describe('Estados das telas', () => {
  test('mostra esqueleto enquanto carrega, nunca rodinha, e erro com tentar de novo que recupera', async ({ page, goto }) => {
    await signIn(page)
    let release!: () => void
    const gate = new Promise<void>((resolve) => { release = resolve })
    let attempts = 0
    await page.route('**/api/v1/roles', (route) => route.fulfill({ status: 200, json: { data: { roles: [], permissions: [] } } }))
    await page.route('**/api/v1/editions/ed-27/team', async (route) => {
      attempts++
      if (attempts === 1) {
        await gate
        return route.fulfill({ status: 500, json: { code: 'server_error', message: 'x', errors: null, request_id: 'r' } })
      }
      return route.fulfill({ status: 200, json: { data: { members: [], invitations: [], summary: { people: 0, pending_invitations: 0, by_role: {} } } } })
    })

    await goto('/equipe', { waitUntil: 'hydration' })
    await expect(page.getByTestId('page-skeleton')).toBeVisible()
    await expect(page.locator('.loading-spinner')).toHaveCount(0)
    release()

    const error = page.getByRole('alert').filter({ hasText: 'Não foi possível carregar a equipe.' })
    await expect(error).toContainText('Nada foi perdido.')
    await error.getByRole('button', { name: 'Tentar de novo' }).click()
    await expect(page.getByRole('table', { name: 'Equipe da edição' })).toBeVisible()
  })

  test('lista vazia sugere a próxima ação', async ({ page, goto }) => {
    await signIn(page)
    await page.route('**/api/v1/editions/ed-27/cfp/**', (route) => {
      const path = new URL(route.request().url()).pathname
      if (path.endsWith('/speakers')) return route.fulfill({ status: 200, json: { summary: { confirmed: 0, awaiting: 0, waitlisted: 0, slots: 0, nordeste_percent: null, nordeste_quota_percent: 50 }, coverage: [], data: [] } })
      return route.fulfill({ status: 200, json: { data: [] } })
    })

    await goto('/palestrantes', { waitUntil: 'hydration' })
    await expect(page.getByRole('heading', { name: 'Nenhuma proposta aprovada ainda' })).toBeVisible()
    await page.getByRole('link', { name: 'Avaliar propostas' }).last().click()
    await expect(page).toHaveURL(/\/cfp\/avaliacao$/)
  })

  test('erros do formulário aparecem no campo e resumidos no topo', async ({ page, goto }) => {
    await signIn(page)
    await page.route('**/api/v1/editions/ed-27**', (route) => {
      const path = new URL(route.request().url()).pathname
      if (path.endsWith('/domains')) return route.fulfill({ status: 200, json: { data: [] } })
      if (route.request().method() === 'PATCH') {
        return route.fulfill({ status: 422, json: { code: 'validation_error', message: 'x', errors: { name: ['O campo nome é obrigatório.'], ends_on: ['O fim precisa ser depois do início.'] }, request_id: 'r' } })
      }
      return route.fulfill({ status: 200, json: { data: { ...edition, status_options: [], timezone: 'America/Fortaleza', site_mode: 'platform', external_site_url: null, privacy_contact_email: null, privacy_policy_url: null, anonymize_after_event: false } } })
    })

    await goto('/configuracoes', { waitUntil: 'hydration' })
    await page.getByLabel('Cidade').fill('Campina Grande')
    await page.getByRole('button', { name: 'Salvar alterações' }).click()

    const summary = page.getByTestId('form-errors')
    await expect(summary).toContainText('2 campos precisam de ajuste')
    await expect(summary).toBeFocused()
    await summary.getByRole('link', { name: 'Fim' }).click()
    await expect(page.locator('#settings-endsOn')).toBeFocused()
    await expect(page.getByText('O fim precisa ser depois do início.').first()).toBeVisible()
  })
})
