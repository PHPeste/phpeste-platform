import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'user-1', name: 'Nome Sobrenome', email: 'n@s.dev', avatarUrl: null }, token: 'e2e-token' }
const edition = {
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHPeste', slug: 'phpeste' },
  status: 'cfp_open', status_label: 'CFP aberto', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role: 'organizador',
}
const sertao = {
  bg: '#FBF4E4', surface: '#FFFBF2', surface_2: '#F5EAD0', line: '#E4D3AB', ink: '#3A1607', ink_2: '#6F4A38', ink_3: '#7F5A47', link: '#9C4220',
  brand: '#6B2A12', on_brand: '#FFF8E8', accent: '#E8B544', on_accent: '#3A1607', support: '#B4532A',
  ok: '#4B6A24', ok_bg: '#E6EBCF', warn: '#8A5A00', warn_bg: '#F8E7B9', danger: '#A12B1F', danger_bg: '#F6D9D2', info: '#2F5D6B', info_bg: '#D9E7E9',
}
const mare = { ...sertao, bg: '#0F1B2D', surface: '#16273F', surface_2: '#1D3250', line: '#27415F', ink: '#F2F6FA', ink_2: '#C3D0DE', ink_3: '#A3B4C7', link: '#7CC4F5', brand: '#2C78CA', on_brand: '#FFFFFF', accent: '#7CC4F5', on_accent: '#0F1B2D', support: '#2C78CA', ok: '#8FD18A', ok_bg: '#183A2A', warn: '#F2C46B', warn_bg: '#3A2F14', danger: '#FF8F85', danger_bg: '#3F1D24', info: '#7CC4F5', info_bg: '#173552' }
const labels: Record<string, string> = { bg: 'Fundo', surface: 'Superfície', surface_2: 'Superfície secundária', line: 'Bordas', ink: 'Texto', ink_2: 'Texto secundário', ink_3: 'Legendas', link: 'Links', brand: 'Marca', on_brand: 'Texto sobre marca', accent: 'Destaque', on_accent: 'Texto sobre destaque', support: 'Apoio', ok: 'Sucesso', ok_bg: 'Fundo de sucesso', warn: 'Atenção', warn_bg: 'Fundo de atenção', danger: 'Erro', danger_bg: 'Fundo de erro', info: 'Informação', info_bg: 'Fundo de informação' }
const pairs = [['ink', 'bg'], ['ink', 'surface'], ['ink', 'surface_2'], ['ink_2', 'bg'], ['ink_2', 'surface'], ['ink_2', 'surface_2'], ['ink_3', 'bg'], ['ink_3', 'surface'], ['link', 'bg'], ['link', 'surface'], ['on_brand', 'brand'], ['on_brand', 'support'], ['on_accent', 'accent'], ['ok', 'ok_bg'], ['warn', 'warn_bg'], ['danger', 'danger_bg'], ['info', 'info_bg'], ['ok', 'surface'], ['warn', 'surface'], ['danger', 'surface'], ['info', 'surface']]
  .map(([foreground, background]) => ({ foreground, background, minimum: 4.5 }))
  .concat([{ foreground: 'brand', background: 'bg', minimum: 3 }, { foreground: 'brand', background: 'surface', minimum: 3 }])

const theme = (colors: Record<string, string>) => ({ colors, fonts: { heading: 'Lexend', body: 'Lexend' }, radius: 10, logos: { primary: null, dark: null, favicon: null, app_icon: null } })
const settings = (draft: Record<string, string>, published: Record<string, string> | null = sertao, unpublished = false) => ({
  data: { edition_id: 'ed-27', draft: theme(draft), published: published ? theme(published) : null, published_at: '2027-03-01T12:00:00Z', has_unpublished_changes: unpublished, can_revert: false },
})

async function openEditor(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, handlers: Record<string, (route: Route) => Promise<void> | void> = {}) {
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition] } }))
  await page.route('**/api/v1/editions/ed-27/overview', (route) => route.fulfill({ status: 200, json: { data: { edition, stage: { current: 'cfp_open', days_until_event: null, steps: [] }, indicators: {}, sections: { tickets_per_week: null, nordeste: null }, pending: [], milestones: [] } } }))
  await page.route('**/api/v1/branding/presets', (route) => route.fulfill({
    status: 200,
    json: {
      data: [{ id: 'sertao', label: 'Sertão', description: 'Padrão da plataforma, tons terrosos', theme: { colors: sertao } }, { id: 'mare', label: 'Maré', description: 'Azul escuro, para edições litorâneas', theme: { colors: mare } }],
      meta: { tokens: Object.entries(labels).map(([id, label]) => ({ id, label })), contrast_pairs: pairs, fonts: ['Lexend', 'Poppins', 'Inter'], radius: { min: 0, max: 24 } },
    },
  }))
  await page.route('**/api/v1/editions/ed-27/theme**', async (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1/editions/ed-27/theme', '') || '/'
    const handler = handlers[`${route.request().method()} ${path}`]
    if (handler) return handler(route)
    return route.fulfill({ status: 200, json: settings(sertao) })
  })
  await goto('/identidade', { waitUntil: 'hydration' })
  await expect(page.getByRole('heading', { name: 'Identidade', level: 1 })).toBeVisible()
}

test.describe('Identidade da edição', () => {
  test('a prévia muda ao vivo enquanto a cor é editada, sem salvar nada', async ({ page, goto }) => {
    const writes: string[] = []
    page.on('request', (request) => { if (request.method() !== 'GET') writes.push(request.url()) })
    await openEditor(page, goto)

    const preview = page.getByTestId('theme-preview')
    await expect(preview).toHaveAttribute('style', /--p-brand: #6B2A12/)

    await page.getByLabel('Marca', { exact: true }).fill('#1D4ED8')

    await expect(preview).toHaveAttribute('style', /--p-brand: #1D4ED8/)
    await expect(page.getByText('Há alterações não publicadas.')).toBeVisible()
    expect(writes).toEqual([])
  })

  test('par abaixo do contraste mínimo bloqueia a publicação e diz por quê', async ({ page, goto }) => {
    await openEditor(page, goto)

    await page.getByLabel('Texto', { exact: true }).fill('#D9C9A8')

    await expect(page.getByRole('button', { name: 'Publicar identidade' })).toBeDisabled()
    await expect(page.getByRole('alert').filter({ hasText: 'Não dá para publicar' })).toContainText('Texto sobre Fundo')
    await expect(page.getByRole('status').filter({ hasText: 'abaixo do mínimo' })).toBeVisible()

    await page.getByLabel('Texto', { exact: true }).fill('#3A1607')
    await expect(page.getByRole('alert').filter({ hasText: 'Não dá para publicar' })).toHaveCount(0)
    await expect(page.getByText('Todos os 23 pares passam no mínimo de contraste.')).toBeVisible()
  })

  test('aplica um preset depois de confirmar', async ({ page, goto }) => {
    await openEditor(page, goto, {
      'POST /draft/preset': async (route) => {
        expect(route.request().postDataJSON()).toEqual({ preset: 'mare' })
        await route.fulfill({ status: 200, json: settings(mare, sertao, true) })
      },
    })

    await page.getByRole('button', { name: /Maré/ }).click()
    await page.getByRole('dialog', { name: 'Maré' }).getByRole('button', { name: 'Aplicar' }).click()

    await expect(page.getByTestId('theme-preview')).toHaveAttribute('style', /--p-bg: #0F1B2D/)
    await expect(page.getByLabel('Fundo', { exact: true })).toHaveValue('#0F1B2D')
  })

  test('publicar salva o rascunho e publica', async ({ page, goto }) => {
    const calls: string[] = []
    await openEditor(page, goto, {
      'PUT /draft': async (route) => {
        calls.push('PUT /draft')
        expect(route.request().postDataJSON().colors.accent).toBe('#F0C060')
        await route.fulfill({ status: 200, json: settings({ ...sertao, accent: '#F0C060' }, sertao, true) })
      },
      'POST /publish': async (route) => {
        calls.push('POST /publish')
        await route.fulfill({ status: 200, json: settings({ ...sertao, accent: '#F0C060' }, { ...sertao, accent: '#F0C060' }, false) })
      },
    })

    await page.getByLabel('Destaque', { exact: true }).fill('#F0C060')
    await page.getByRole('button', { name: 'Publicar identidade' }).click()

    await expect(page.getByText('Identidade publicada.')).toBeVisible()
    expect(calls).toEqual(['PUT /draft', 'POST /publish'])
    await expect(page.getByText('Há alterações não publicadas.')).toHaveCount(0)
  })

  test('avisa ao sair com alterações não salvas e deixa continuar editando', async ({ page, goto }) => {
    await openEditor(page, goto)

    await page.getByLabel('Marca', { exact: true }).fill('#1D4ED8')
    await page.getByRole('navigation', { name: 'Principal' }).getByRole('link', { name: 'Visão geral' }).click()

    const dialog = page.getByRole('dialog', { name: 'Sair sem publicar?' })
    await expect(dialog).toContainText('alterações que ainda não foram salvas')
    await dialog.getByRole('button', { name: 'Continuar editando' }).click()
    await expect(page).toHaveURL(/\/identidade$/)

    await page.getByRole('navigation', { name: 'Principal' }).getByRole('link', { name: 'Visão geral' }).click()
    await page.getByRole('dialog', { name: 'Sair sem publicar?' }).getByRole('button', { name: 'Sair mesmo assim' }).click()
    await expect(page).toHaveURL(/\/$/)
  })

  test('envia o logo principal e mostra a prévia', async ({ page, goto }) => {
    await openEditor(page, goto, {
      'POST /logos/primary': (route) => route.fulfill({
        status: 200,
        json: { data: { ...settings(sertao).data, draft: { ...theme(sertao), logos: { primary: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', dark: null, favicon: null, app_icon: null } } } },
      }),
    })

    await page.locator('input[type="file"]').first().setInputFiles({ name: 'logo.png', mimeType: 'image/png', buffer: Buffer.from('fake') })

    await expect(page.getByRole('img', { name: 'Logo principal' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Trocar Logo principal' })).toBeVisible()
  })
})
