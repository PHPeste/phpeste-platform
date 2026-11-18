import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page } from '@playwright/test'

const session = { user: { id: 'user-1', name: 'Nome Sobrenome', email: 'n@s.dev', avatarUrl: null }, token: 'e2e-token' }

const phpeste = { id: 'c-phpeste', name: 'PHPeste', slug: 'phpeste' }
const phppb = { id: 'c-pb', name: 'PHP-PB', slug: 'php-pb' }

const edition = (overrides: Record<string, unknown>) => ({
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: phpeste.id, community: phpeste, status: 'cfp_open', status_label: 'CFP aberto',
  starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role: 'organizador', ...overrides,
})

const editions = [
  edition({}),
  edition({ id: 'ed-26', slug: '2026', name: 'PHPeste 2026', status: 'closed', status_label: 'Encerrada', city: 'Recife', state: 'PE', year: 2026, starts_on: '2026-09-25', ends_on: '2026-09-26' }),
  edition({ id: 'ed-pinga', slug: 'phpinga-2027', name: 'PHPinga 2027', community_id: phppb.id, community: phppb, status: 'draft', status_label: 'Rascunho', role: 'avaliador', starts_on: null, ends_on: null }),
]

async function signIn(page: Page, data: unknown[] = editions, managed: unknown[] = [phpeste]) {
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data } }))
  await page.route('**/api/v1/communities', (route) =>
    route.request().method() === 'GET' ? route.fulfill({ status: 200, json: { data: managed } }) : route.fallback())
}

test.describe('Comunidades e edições', () => {
  test('lista comunidades e edições com etapa e papel', async ({ page, goto }) => {
    await signIn(page)
    await goto('/comunidades', { waitUntil: 'hydration' })

    await expect(page.getByRole('heading', { name: 'Escolha uma edição' })).toBeVisible()
    await expect(page.getByText('Você participa de 2 comunidades.')).toBeVisible()

    const phpesteCard = page.getByRole('region', { name: 'PHPeste' })
    await expect(phpesteCard.getByText('phpeste · Organizador')).toBeVisible()
    await expect(phpesteCard.getByText('8 e 9 out 2027 · João Pessoa (PB)')).toBeVisible()
    await expect(phpesteCard.getByText('CFP aberto')).toBeVisible()
    await expect(phpesteCard.getByRole('button', { name: 'Ver histórico PHPeste 2026' })).toBeVisible()
    await expect(phpesteCard.getByRole('button', { name: 'Nova edição PHPeste' })).toBeVisible()

    const pbCard = page.getByRole('region', { name: 'PHP-PB' })
    await expect(pbCard.getByText('php-pb · Avaliador')).toBeVisible()
    await expect(pbCard.getByText('Rascunho')).toBeVisible()
    await expect(pbCard.getByRole('button', { name: /Nova edição/ })).toHaveCount(0)
  })

  test('abre a edição escolhida no painel', async ({ page, goto }) => {
    await signIn(page)
    await goto('/comunidades', { waitUntil: 'hydration' })

    await page.getByRole('button', { name: 'Abrir PHPinga 2027' }).click()

    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('button', { name: 'Trocar de edição' })).toContainText('PHPinga 2027')
  })

  test('edição encerrada abre em modo leitura com aviso', async ({ page, goto }) => {
    await signIn(page)
    await goto('/comunidades', { waitUntil: 'hydration' })

    await page.getByRole('button', { name: 'Ver histórico PHPeste 2026' }).click()

    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('status').filter({ hasText: 'Edição encerrada.' })).toContainText('modo leitura')
  })

  test('pessoa sem nenhuma edição vê o estado vazio com orientação', async ({ page, goto }) => {
    await signIn(page, [], [])
    await goto('/comunidades', { waitUntil: 'hydration' })

    await expect(page.getByRole('heading', { name: 'Você ainda não participa de nenhuma edição' })).toBeVisible()
    await expect(page.getByText('use o link do e-mail de convite')).toBeVisible()
  })

  test('cria uma comunidade', async ({ page, goto }) => {
    let created = false
    await signIn(page, [], [])
    await page.route('**/api/v1/communities', async (route) => {
      if (route.request().method() === 'POST') {
        expect(route.request().postDataJSON()).toEqual({ name: 'PHP Sergipe', slug: 'php-sergipe' })
        created = true
        return route.fulfill({ status: 201, json: { data: { id: 'c-se', name: 'PHP Sergipe', slug: 'php-sergipe', created_at: '' } } })
      }
      return route.fulfill({ status: 200, json: { data: created ? [{ id: 'c-se', name: 'PHP Sergipe', slug: 'php-sergipe', created_at: '' }] : [] } })
    })
    await goto('/comunidades', { waitUntil: 'hydration' })

    await page.getByRole('button', { name: 'Nova comunidade' }).first().click()
    const dialog = page.getByRole('dialog', { name: 'Nova comunidade' })
    await dialog.getByLabel('Nome da comunidade').fill('PHP Sergipe')
    await expect(dialog.getByLabel('Endereço curto')).toHaveValue('php-sergipe')
    await dialog.getByRole('button', { name: 'Criar' }).click()

    await expect(page.getByRole('region', { name: 'PHP Sergipe' })).toBeVisible()
    await expect(page.getByRole('region', { name: 'PHP Sergipe' }).getByText('Nenhuma edição ainda.')).toBeVisible()
  })

  test('nova edição copiando da anterior mostra a prévia do que vai e do que nunca vai', async ({ page, goto }) => {
    await signIn(page)
    await page.route('**/api/v1/editions/ed-27/clone-options', (route) => route.fulfill({
      status: 200,
      json: { data: { parts: [{ id: 'identity', label: 'Identidade: tema e logos' }, { id: 'team', label: 'Equipe e papéis' }, { id: 'cfp', label: 'CFP: regras, trilhas e critérios' }], never_copied: ['Propostas', 'Participantes', 'Datas'] } },
    }))
    await page.route('**/api/v1/editions/ed-27/clone', async (route) => {
      expect(route.request().postDataJSON()).toEqual({ name: 'PHPeste 2028', slug: 'phpeste-2028', parts: ['identity', 'cfp'] })
      await route.fulfill({ status: 202, json: { data: { id: 'clone-1', status: 'queued', target_edition_id: null, error: null } } })
    })
    let polls = 0
    await page.route('**/api/v1/edition-clones/clone-1', (route) => route.fulfill({
      status: 200,
      json: { data: { id: 'clone-1', status: ++polls > 1 ? 'done' : 'queued', target_edition_id: polls > 1 ? 'ed-27' : null, error: null } },
    }))
    await goto('/comunidades', { waitUntil: 'hydration' })

    await page.getByRole('button', { name: 'Nova edição PHPeste' }).click()
    const dialog = page.getByRole('dialog', { name: 'Nova edição em PHPeste' })
    await expect(dialog.getByLabel('Copiar de uma edição anterior')).toBeChecked()
    await expect(dialog.getByLabel('Copiar de', { exact: true })).toHaveValue('ed-27')
    await dialog.getByRole('button', { name: 'Continuar' }).click()

    await dialog.getByLabel('Nome da edição').fill('PHPeste 2028')
    await dialog.getByRole('button', { name: 'Continuar' }).click()

    await dialog.getByLabel('Equipe e papéis').uncheck()
    const preview = dialog.locator('[aria-live="polite"]')
    await expect(preview.getByRole('listitem')).toHaveText(['Identidade: tema e logos', 'CFP: regras, trilhas e critérios', 'Propostas', 'Participantes', 'Datas'])
    await dialog.getByRole('button', { name: 'Criar edição' }).click()

    await expect(page).toHaveURL(/\/$/)
  })

  test('nova edição do zero com datas, cidade e UF', async ({ page, goto }) => {
    await signIn(page, editions, [phpeste, { id: 'c-novo', name: 'PHP-RN', slug: 'php-rn' }])
    await page.route('**/api/v1/communities/c-novo/editions', async (route) => {
      expect(route.request().postDataJSON()).toEqual({ name: 'PHPeste RN 2028', slug: 'phpeste-rn-2028', starts_on: '2028-05-12', ends_on: '2028-05-11', city: 'Natal', state: 'RN' })
      await route.fulfill({ status: 422, json: { code: 'validation_error', message: 'x', errors: { ends_on: ['O término precisa ser depois do início.'] }, request_id: 'r' } })
    })
    await goto('/comunidades', { waitUntil: 'hydration' })

    await page.getByRole('button', { name: 'Nova edição PHP-RN' }).click()
    const dialog = page.getByRole('dialog', { name: 'Nova edição em PHP-RN' })
    await expect(dialog.getByText('Ponto de partida')).toHaveCount(0)
    await dialog.getByLabel('Nome da edição').fill('PHPeste RN 2028')
    await dialog.getByLabel('Começa em').fill('2028-05-12')
    await dialog.getByLabel('Termina em').fill('2028-05-11')
    await dialog.getByLabel('Cidade').fill('Natal')
    await dialog.getByLabel('UF').selectOption('RN')
    await dialog.getByRole('button', { name: 'Criar edição' }).click()

    await expect(dialog.getByText('O término precisa ser no mesmo dia ou depois do início.')).toBeVisible()
  })
})
