import { expect, test } from '@nuxt/test-utils/playwright'

// The backend is mocked via page.route() — no real API needs to be running.
// This is what actually renders in a browser: layout, hydration, focus/blur
// behavior and real DOM state, which the Vitest component tests (mocking
// the composable) can't catch on their own.
const LOGIN_API = '**/api/v1/auth/login'

test.describe('Login', () => {
  test('renders the login form', async ({ page, goto }) => {
    await goto('/login', { waitUntil: 'hydration' })

    await expect(page.getByRole('heading', { name: 'Entrar no painel' })).toBeVisible()
    await expect(page.getByLabel('E-mail')).toBeVisible()
    await expect(page.getByLabel('Senha', { exact: true })).toBeVisible()
  })

  test('shows validation errors on empty submit', async ({ page, goto }) => {
    await goto('/login', { waitUntil: 'hydration' })

    await page.getByRole('button', { name: 'Entrar', exact: true }).click()

    await expect(page.getByText('Informe o e-mail.')).toBeVisible()
    await expect(page.getByText('Informe a senha.')).toBeVisible()
  })

  test('shows an invalid-email error for a malformed address', async ({ page, goto }) => {
    await goto('/login', { waitUntil: 'hydration' })

    await page.getByLabel('E-mail').fill('not-an-email')
    await page.getByLabel('E-mail').blur()

    await expect(page.getByText('Este e-mail não parece válido.')).toBeVisible()
  })

  test('toggles password visibility', async ({ page, goto }) => {
    await goto('/login', { waitUntil: 'hydration' })

    const password = page.getByLabel('Senha', { exact: true })
    await expect(password).toHaveAttribute('type', 'password')

    await page.getByRole('button', { name: 'Mostrar senha' }).click()

    await expect(password).toHaveAttribute('type', 'text')
    await expect(page.getByRole('button', { name: 'Ocultar senha' })).toBeVisible()
  })

  test('shows an error message on invalid credentials', async ({ page, goto }) => {
    await page.route(LOGIN_API, (route) =>
      route.fulfill({ status: 401, json: { code: 'unauthenticated', message: 'Credenciais inválidas.', errors: null, request_id: 'r' } }))

    await goto('/login', { waitUntil: 'hydration' })

    await page.getByLabel('E-mail').fill('ada@example.com')
    await page.getByLabel('Senha', { exact: true }).fill('wrong-password')
    await page.getByRole('button', { name: 'Entrar', exact: true }).click()

    await expect(page.getByRole('alert').filter({ hasText: 'E-mail ou senha inválidos.' })).toBeVisible()
    await expect(page).toHaveURL(/\/login$/)
  })

  test('logs in and lands on the edition picker', async ({ page, goto }) => {
    await page.route(LOGIN_API, (route) =>
      route.fulfill({
        status: 200,
        json: {
          user: { id: 'user-1', name: 'Ada Lovelace', email: 'ada@example.com', avatar_url: null, created_at: '2026-01-01' },
          token: 'e2e-test-token',
        },
      }))

    await goto('/login', { waitUntil: 'hydration' })

    await page.getByLabel('E-mail').fill('ada@example.com')
    await page.getByLabel('Senha', { exact: true }).fill('correct-password')
    await page.getByRole('button', { name: 'Entrar', exact: true }).click()

    await expect(page).toHaveURL(/\/comunidades$/)
    await expect(page.getByRole('heading', { name: 'Escolha uma edição' })).toBeVisible()
    await expect(page.getByRole('banner')).toContainText('Ada Lovelace')
  })

  test('links to password recovery and keeps the session only for this tab when "manter conectado" is off', async ({ page, goto }) => {
    await page.route(LOGIN_API, (route) =>
      route.fulfill({
        status: 200,
        json: { user: { id: 'user-1', name: 'Ada Lovelace', email: 'ada@example.com', avatar_url: null, created_at: '2026-01-01' }, token: 'tab-token' },
      }))

    await goto('/login', { waitUntil: 'hydration' })
    await expect(page.getByRole('link', { name: 'Esqueci a senha' })).toHaveAttribute('href', '/esqueci-senha')

    await page.getByLabel('E-mail').fill('ada@example.com')
    await page.getByLabel('Senha', { exact: true }).fill('correct-password')
    await page.getByLabel('Manter conectado neste dispositivo').uncheck()
    await page.getByRole('button', { name: 'Entrar', exact: true }).click()

    await expect(page).toHaveURL(/\/comunidades$/)
    expect(await page.evaluate(() => [localStorage.getItem('app.auth'), sessionStorage.getItem('app.auth')])).toEqual([null, expect.stringContaining('tab-token')])
  })

  test('logs in with GitHub: authorization, return with code and state, session saved', async ({ page, goto }) => {
    await page.route('**/api/v1/auth/github/redirect', (route) =>
      route.fulfill({ status: 200, json: { url: '/login/github?code=gh-code&state=gh-state' } }))
    await page.route('**/api/v1/auth/github/callback', async (route) => {
      expect(route.request().postDataJSON()).toMatchObject({ code: 'gh-code', state: 'gh-state' })
      await route.fulfill({
        status: 200,
        json: { user: { id: 'user-2', name: 'Grace Hopper', email: 'grace@example.com', avatar_url: null, created_at: '2026-01-01' }, token: 'gh-token' },
      })
    })

    await goto('/login', { waitUntil: 'hydration' })
    await page.getByRole('button', { name: 'Entrar com GitHub' }).click()

    await expect(page).toHaveURL(/\/comunidades$/)
    await expect(page.getByRole('banner')).toContainText('Grace Hopper')
  })

  test('explains why the GitHub login was refused', async ({ page, goto }) => {
    await page.route('**/api/v1/auth/github/callback', (route) =>
      route.fulfill({
        status: 422,
        json: { code: 'validation_error', message: 'Os dados enviados são inválidos.', errors: { github: ['Sua conta do GitHub não tem um e-mail principal verificado.'] }, request_id: 'r' },
      }))

    await goto('/login/github?code=c&state=s', { waitUntil: 'hydration' })

    await expect(page.getByRole('alert')).toHaveText('Sua conta do GitHub não tem um e-mail principal verificado.')
    await expect(page.getByRole('link', { name: 'Voltar para a entrada' })).toBeVisible()
  })
})
