import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page } from '@playwright/test'

const EDITIONS_API = '**/api/v1/editions'
const LOGIN_API = '**/api/v1/auth/login'

const editions = [
  { id: 1, slug: 'ed-1', name: 'PHPeste 2027', city: 'João Pessoa', state: 'PB', year: 2027, site_url: 'https://phpeste.dev', role: 'organizador' },
  { id: 2, slug: 'ed-2', name: 'PHPeste 2026', city: 'Recife', state: 'PE', year: 2026, site_url: null, role: 'financeiro' },
]

const session = {
  user: { id: 1, name: 'Nome Sobrenome', email: 'n@s.dev', avatarUrl: null },
  token: 'e2e-token',
}

async function signIn(page: Page, data = editions) {
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route(EDITIONS_API, (route) => route.fulfill({ status: 200, json: { data } }))
}

test.describe('Layout base do painel', () => {
  test('mostra menu lateral com seções, item ativo e rodapé da edição', async ({ page, goto }) => {
    await signIn(page)
    await goto('/', { waitUntil: 'hydration' })

    const nav = page.getByRole('navigation', { name: 'Principal' })
    await expect(nav).toBeVisible()
    for (const group of ['Edição', 'Conteúdo', 'Vendas', 'Organização', 'Sistema']) {
      await expect(nav.getByRole('heading', { name: group })).toBeVisible()
    }
    await expect(nav.getByRole('link', { name: 'Visão geral' })).toHaveAttribute('aria-current', 'page')
    await expect(nav.getByRole('link')).toHaveCount(17)
    const width = await page.locator('aside').evaluate((el) => el.getBoundingClientRect().width)
    expect(width).toBe(264)
    await expect(page.locator('aside').getByText('PHPeste 2027 · João Pessoa')).toBeVisible()
  })

  test('barra superior: seletor de edição, busca, ver site, notificações e usuário', async ({ page, goto }) => {
    await signIn(page)
    await goto('/', { waitUntil: 'hydration' })

    await expect(page.getByRole('button', { name: 'Trocar de edição' })).toContainText('PHPeste 2027')
    await expect(page.getByRole('searchbox', { name: 'Buscar' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Ver site da edição' })).toHaveAttribute('href', 'https://phpeste.dev')
    await expect(page.getByRole('button', { name: 'Notificações' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Menu do usuário' })).toContainText('Nome Sobrenome')
  })

  test('trocar de edição muda o papel, o menu e mantém a rota', async ({ page, goto }) => {
    await signIn(page)
    await goto('/', { waitUntil: 'hydration' })
    const nav = page.getByRole('navigation', { name: 'Principal' })

    await page.getByRole('button', { name: 'Trocar de edição' }).click()
    await page.getByRole('list', { name: 'Edições' }).getByRole('button', { name: /PHPeste 2026/ }).click()

    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('button', { name: 'Trocar de edição' })).toContainText('PHPeste 2026')
    await expect(nav.getByRole('link')).toHaveCount(5)
    await expect(nav.getByRole('link', { name: 'Configurar CFP' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Menu do usuário' })).toContainText('Financeiro')
  })

  test('papel sem permissão não vê o item e a rota devolve 403 com ação de voltar', async ({ page, goto }) => {
    await signIn(page, [{ ...editions[1]! }])
    await goto('/', { waitUntil: 'hydration' })
    await expect(page.getByRole('navigation', { name: 'Principal' }).getByRole('link', { name: 'Pedidos' })).toBeVisible()

    await page.goto('/cfp')
    await expect(page.getByRole('heading', { name: 'Sem permissão' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Voltar' })).toBeVisible()
    await page.getByRole('button', { name: 'Ir para a visão geral' }).click()
    await expect(page.getByRole('heading', { name: 'Visão geral' })).toBeVisible()
  })

  test('rota inexistente mostra 404', async ({ page, goto }) => {
    await signIn(page)
    await goto('/nao-existe', { waitUntil: 'hydration' })
    await expect(page.getByRole('heading', { name: 'Página não encontrada' })).toBeVisible()
  })

  test('sem token redireciona ao login e volta para a rota depois de entrar', async ({ page, goto }) => {
    await page.route(EDITIONS_API, (route) => route.fulfill({ status: 200, json: { data: editions } }))
    await page.route(LOGIN_API, (route) =>
      route.fulfill({
        status: 200,
        json: { user: { id: 1, name: 'Nome Sobrenome', email: 'n@s.dev', avatar_url: null, created_at: '2026-01-01' }, token: 'e2e-token' },
      }))

    await goto('/pedidos', { waitUntil: 'hydration' })
    await expect(page).toHaveURL(/\/login\?redirect=(%2F|\/)pedidos$/)

    await page.getByLabel('Email').fill('n@s.dev')
    await page.getByLabel('Senha', { exact: true }).fill('segredo123')
    await page.getByRole('button', { name: 'Entrar' }).click()

    await expect(page).toHaveURL(/\/pedidos$/)
  })

  test('em tela estreita o menu vira gaveta aberta por botão', async ({ page, goto }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await signIn(page)
    await goto('/', { waitUntil: 'hydration' })

    await expect(page.locator('aside')).toBeHidden()
    const opener = page.getByRole('button', { name: 'Abrir menu' })
    await opener.click()
    const drawer = page.getByRole('dialog', { name: 'Principal' })
    await expect(drawer).toBeVisible()
    await expect(drawer.getByRole('link', { name: 'Visão geral' })).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(drawer).toBeHidden()
    await expect(opener).toBeFocused()
  })

  test('navegação completa por teclado: pular ao conteúdo, menu e seletor', async ({ page, goto }) => {
    await signIn(page)
    await goto('/', { waitUntil: 'hydration' })

    await page.keyboard.press('Tab')
    await expect(page.getByRole('link', { name: 'Ir para o conteúdo' })).toBeFocused()

    const edition = page.getByRole('button', { name: 'Trocar de edição' })
    await edition.focus()
    await page.keyboard.press('Enter')
    await expect(edition).toHaveAttribute('aria-expanded', 'true')
    await page.keyboard.press('Escape')
    await expect(edition).toHaveAttribute('aria-expanded', 'false')
    await expect(edition).toBeFocused()

    const first = page.getByRole('navigation', { name: 'Principal' }).getByRole('link').first()
    await first.focus()
    await page.keyboard.press('Tab')
    await expect(page.getByRole('navigation', { name: 'Principal' }).getByRole('link', { name: 'Identidade visual' })).toBeFocused()
  })
})
