import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Nome Sobrenome', email: 'n@s.dev', avatarUrl: null }, token: 'e2e-token' }
const edition = {
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHPeste', slug: 'phpeste' },
  status: 'cfp_open', status_label: 'CFP aberto', starts_on: null, ends_on: null, city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role: 'organizador',
}
const form = {
  formats: [{ id: 'talk', label: 'Palestra de 30 min', minutes: 30 }, { id: 'workshop', label: 'Oficina de 2 h', minutes: 120 }],
  levels: [{ id: 'beginner', label: 'Iniciante' }, { id: 'advanced', label: 'Avançado' }],
  languages: [{ id: 'pt_BR', label: 'Português' }],
  limits: { title: 90, summary: 3000, audience: 1000 },
}
const settings = (overrides: Record<string, unknown> = {}) => ({
  data: {
    status: 'open', status_label: 'CFP aberto', days_until_close: 36,
    opens_at: '2027-02-01T03:00:00Z', closes_at: '2027-04-16T02:59:59Z', results_at: '2027-05-01T02:59:59Z',
    anonymous_review: true, require_nordeste_quota: true, nordeste_quota_percent: 50, proposals_per_person: 2, ...overrides,
  },
  meta: { form },
})

interface Db {
  tracks: Array<{ id: string, name: string, slots: number, position: number, proposals_count: number }>
  criteria: Array<{ id: string, name: string, description: string | null, weight: number, position: number }>
  reviews: number
}

async function openCfp(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, db: Db, handlers: Record<string, (route: Route) => Promise<void> | void> = {}) {
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition] } }))
  await page.route('**/api/v1/editions/ed-27/overview', (route) => route.abort())
  await page.route('**/api/v1/editions/ed-27/cfp/**', async (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1/editions/ed-27/cfp', '')
    const handler = handlers[`${route.request().method()} ${path}`]
    if (handler) return handler(route)
    if (path === '/settings') return route.fulfill({ status: 200, json: settings() })
    if (path === '/tracks') return route.fulfill({ status: 200, json: { data: [...db.tracks].sort((a, b) => a.position - b.position) } })
    if (path === '/criteria') return route.fulfill({ status: 200, json: { data: db.criteria, meta: { submitted_reviews: db.reviews } } })
    return route.fulfill({ status: 404, json: {} })
  })
  await goto('/cfp', { waitUntil: 'hydration' })
  await expect(page.getByRole('heading', { name: 'Configuração do CFP', level: 1 })).toBeVisible()
}

const emptyDb = (): Db => ({ tracks: [], criteria: [], reviews: 0 })

test.describe('Configuração do CFP', () => {
  test('valida a janela, mantém o que foi editado ao trocar de aba e salva', async ({ page, goto }) => {
    let body: Record<string, unknown> | null = null
    const db = { ...emptyDb(), tracks: [{ id: 't-1', name: 'Backend e arquitetura', slots: 6, position: 1, proposals_count: 14 }] }
    await openCfp(page, goto, db, {
      'PATCH /settings': async (route) => {
        body = route.request().postDataJSON()
        await route.fulfill({ status: 200, json: settings({ proposals_per_person: 3, closes_at: '2027-04-21T02:59:59Z' }) })
      },
    })

    await expect(page.getByLabel('Abre em')).toHaveValue('2027-02-01')
    await expect(page.getByLabel('Fecha em')).toHaveValue('2027-04-15')
    await expect(page.getByText('Faltam 36 dias para fechar')).toBeVisible()

    await page.getByLabel('Fecha em').fill('2027-01-10')
    await expect(page.getByText('O fechamento precisa ser depois da abertura.')).toBeVisible()
    await page.getByLabel('Fecha em').fill('2027-04-20')
    await page.getByLabel('Propostas por pessoa').fill('3')

    await page.getByRole('tab', { name: 'Trilhas' }).click()
    await expect(page.getByRole('row', { name: /Backend e arquitetura.*14.*6/ })).toBeVisible()
    await page.getByRole('tab', { name: 'Formulário' }).click()
    await expect(page.getByTestId('form-preview')).toContainText('Cada pessoa pode enviar até 3 propostas.')
    await expect(page.getByTestId('form-preview')).toContainText('Envios até 20 de abril.')

    await page.getByRole('tab', { name: /Geral/ }).click()
    await expect(page.getByLabel('Propostas por pessoa')).toHaveValue('3')
    await expect(page.getByText('Alterações não salvas em Geral')).toBeVisible()
    await page.getByRole('button', { name: 'Salvar alterações' }).click()

    await expect(page.getByText('Configuração do CFP salva.')).toBeVisible()
    expect(body).toMatchObject({ opens_at: '2027-02-01T03:00:00.000Z', closes_at: '2027-04-21T02:59:59.000Z', proposals_per_person: 3, anonymous_review: true })
    await expect(page.getByText('Alterações não salvas em Geral')).toHaveCount(0)
  })

  test('cria, edita, reordena e remove trilhas', async ({ page, goto }) => {
    const db = { ...emptyDb(), tracks: [{ id: 't-1', name: 'Backend e arquitetura', slots: 6, position: 1, proposals_count: 14 }] }
    await openCfp(page, goto, db, {
      'POST /tracks': async (route) => {
        const { name, slots } = route.request().postDataJSON()
        db.tracks.push({ id: 't-2', name, slots, position: 2, proposals_count: 0 })
        await route.fulfill({ status: 201, json: { data: db.tracks[1] } })
      },
      'PATCH /tracks/t-1': async (route) => {
        Object.assign(db.tracks[0]!, route.request().postDataJSON())
        await route.fulfill({ status: 200, json: { data: db.tracks[0] } })
      },
      'PATCH /tracks/t-2': async (route) => {
        Object.assign(db.tracks[1]!, route.request().postDataJSON())
        await route.fulfill({ status: 200, json: { data: db.tracks[1] } })
      },
      'DELETE /tracks/t-1': async (route) => {
        db.tracks = db.tracks.filter((track) => track.id !== 't-1')
        await route.fulfill({ status: 204 })
      },
    })
    await page.getByRole('tab', { name: 'Trilhas' }).click()

    await page.getByRole('button', { name: 'Nova trilha' }).click()
    const create = page.getByRole('dialog', { name: 'Nova trilha' })
    await create.getByLabel('Nome da trilha').fill('Qualidade e testes')
    await create.getByLabel('Vagas na grade').fill('4')
    await create.getByRole('button', { name: 'Salvar trilha' }).click()
    await expect(page.getByText('Trilha "Qualidade e testes" salva.')).toBeVisible()

    await page.getByRole('button', { name: 'Subir Qualidade e testes' }).click()
    await expect(page.getByRole('table', { name: 'Trilhas' }).locator('tbody tr').first()).toContainText('Qualidade e testes')

    await page.getByRole('button', { name: 'Editar Backend e arquitetura' }).click()
    await page.getByRole('dialog', { name: 'Editar trilha' }).getByRole('button', { name: 'Remover trilha' }).click()
    const remove = page.getByRole('dialog', { name: 'Remover a trilha "Backend e arquitetura"?' })
    await expect(remove).toContainText('As 14 propostas enviadas nela ficam sem trilha')
    await remove.getByRole('button', { name: 'Remover trilha' }).click()
    await expect(page.getByText('Trilha "Backend e arquitetura" removida.')).toBeVisible()
    await expect(page.getByRole('row', { name: /Backend e arquitetura/ })).toHaveCount(0)
  })

  test('mudar o peso com avaliações existentes mostra o impacto e só salva depois de confirmar', async ({ page, goto }) => {
    const db = { ...emptyDb(), reviews: 12, criteria: [{ id: 'c-1', name: 'Relevância para a comunidade', description: 'O tema interessa?', weight: 3, position: 1 }, { id: 'c-2', name: 'Clareza da proposta', description: null, weight: 1, position: 2 }] }
    const bodies: unknown[] = []
    await openCfp(page, goto, db, {
      'PATCH /criteria/c-1': async (route) => {
        bodies.push(route.request().postDataJSON())
        Object.assign(db.criteria[0]!, { weight: 5 })
        await route.fulfill({ status: 200, json: { data: db.criteria[0] } })
      },
    })
    await page.getByRole('tab', { name: 'Critérios' }).click()

    await expect(page.getByText('Já existem 12 avaliações enviadas.')).toBeVisible()
    await expect(page.getByRole('listitem').filter({ hasText: 'Relevância' })).toContainText('75% da nota')

    await page.getByRole('button', { name: 'Editar Relevância para a comunidade' }).click()
    await page.getByRole('dialog', { name: 'Editar critério' }).getByLabel('Peso').selectOption('5')
    await page.getByRole('dialog', { name: 'Editar critério' }).getByRole('button', { name: 'Salvar critério' }).click()

    const impact = page.getByRole('dialog', { name: 'Mudar critérios com avaliações' })
    await expect(impact).toContainText('Já existem 12 avaliações enviadas.')
    await expect(impact).toContainText('recalcula a nota final de todas as propostas avaliadas')
    expect(bodies).toEqual([])

    await impact.getByRole('button', { name: 'Entendi, aplicar' }).click()
    await expect(page.getByText('Critério "Relevância para a comunidade" salvo.')).toBeVisible()
    expect(bodies).toEqual([{ name: 'Relevância para a comunidade', description: 'O tema interessa?', weight: 5, confirm: true }])
    await expect(page.getByRole('listitem').filter({ hasText: 'Relevância' })).toContainText('83% da nota')
  })

  test('a prévia do formulário usa as trilhas e as opções da API', async ({ page, goto }) => {
    await openCfp(page, goto, { ...emptyDb(), tracks: [{ id: 't-1', name: 'Mobile e PHP fora da web', slots: 4, position: 1, proposals_count: 0 }] })

    await page.getByRole('button', { name: 'Ver formulário público' }).click()
    const preview = page.getByTestId('form-preview')
    await expect(preview.getByLabel('Trilha')).toContainText('Mobile e PHP fora da web')
    await expect(preview.getByLabel('Formato')).toContainText('Oficina de 2 h')
    await expect(preview).toContainText('até 3.000 caracteres')
    await expect(preview).toContainText('A avaliação é anônima')
  })

  test('avisa ao sair com alteração não salva em Geral', async ({ page, goto }) => {
    await openCfp(page, goto, emptyDb())

    await page.getByLabel('Propostas por pessoa').fill('5')
    await page.getByRole('navigation', { name: 'Principal' }).getByRole('link', { name: 'Visão geral' }).click()

    const dialog = page.getByRole('dialog', { name: 'Sair sem salvar?' })
    await dialog.getByRole('button', { name: 'Continuar editando' }).click()
    await expect(page).toHaveURL(/\/cfp$/)
    await expect(page.getByLabel('Propostas por pessoa')).toHaveValue('5')
  })
})
