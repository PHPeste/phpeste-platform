import { expect, test } from '@nuxt/test-utils/playwright'

const INVITATION = {
  data: {
    edition: { id: 'ed-1', name: 'PHPeste 2027' },
    email: 'livia@comunidade.dev',
    name: 'Lívia Ferraz',
    role: 'financeiro',
    role_label: 'Financeiro',
    invited_by: 'Mariana Sales',
    account_exists: false,
    expires_at: '2027-11-10T15:00:00Z',
  },
}

test.describe('Aceite de convite', () => {
  test('cria a conta com os dados do convite e entra no painel', async ({ page, goto }) => {
    await page.route('**/api/v1/invitations/tok-novo', (route) => route.fulfill({ status: 200, json: INVITATION }))
    await page.route('**/api/v1/invitations/tok-novo/accept', async (route) => {
      expect(route.request().postDataJSON()).toMatchObject({ name: 'Lívia Ferraz', password: 'senha123456', password_confirmation: 'senha123456' })
      await route.fulfill({ status: 200, json: { user: { id: 'user-9', name: 'Lívia Ferraz', email: 'livia@comunidade.dev' }, edition_id: 'ed-1', token: 'invite-token' } })
    })

    await goto('/convite/tok-novo', { waitUntil: 'hydration' })

    await expect(page.getByRole('heading', { name: 'Você recebeu um convite para a equipe' })).toBeVisible()
    await expect(page.getByText('Mariana Sales convidou você como Financeiro no PHPeste 2027.')).toBeVisible()
    await expect(page.getByLabel('Nome')).toHaveValue('Lívia Ferraz')
    await expect(page.getByLabel('E-mail')).toHaveValue('livia@comunidade.dev')
    await expect(page.getByText('Convite válido até 10 nov 2027.')).toBeVisible()

    await page.getByLabel('Crie uma senha').fill('senha123456')
    await page.getByLabel('Repita a senha').fill('senha123456')
    await page.getByRole('button', { name: 'Aceitar convite e entrar' }).click()

    await expect(page).toHaveURL(/\/comunidades$/)
    await expect(page.getByRole('banner')).toContainText('Lívia Ferraz')
  })

  test('com conta existente pede a senha e mostra o erro de senha errada', async ({ page, goto }) => {
    await page.route('**/api/v1/invitations/tok-conta', (route) =>
      route.fulfill({ status: 200, json: { data: { ...INVITATION.data, account_exists: true } } }))
    await page.route('**/api/v1/invitations/tok-conta/accept', (route) =>
      route.fulfill({
        status: 422,
        json: { code: 'validation_error', message: 'Os dados enviados são inválidos.', errors: { password: ['Senha incorreta para a conta deste e-mail.'] }, request_id: 'r' },
      }))

    await goto('/convite/tok-conta', { waitUntil: 'hydration' })

    await expect(page.getByLabel('Nome')).toHaveCount(0)
    await page.getByLabel('Sua senha').fill('senha-errada')
    await page.getByRole('button', { name: 'Aceitar convite e entrar' }).click()

    await expect(page.getByRole('alert')).toHaveText('Senha incorreta para a conta deste e-mail.')
    await expect(page.getByLabel('Sua senha')).toHaveAttribute('aria-invalid', 'true')
    await expect(page).toHaveURL(/\/convite\/tok-conta$/)
  })

  test('convite expirado explica o motivo e não mostra o formulário', async ({ page, goto }) => {
    await page.route('**/api/v1/invitations/tok-velho', (route) =>
      route.fulfill({ status: 410, json: { code: 'gone', message: 'Este convite expirou. Peça um novo para a organização.', errors: null, request_id: 'r' } }))

    await goto('/convite/tok-velho', { waitUntil: 'hydration' })

    await expect(page.getByRole('heading', { name: 'Convite indisponível' })).toBeVisible()
    await expect(page.getByRole('alert')).toHaveText('Este convite expirou. Peça um novo para a organização.')
    await expect(page.getByRole('button', { name: 'Aceitar convite e entrar' })).toHaveCount(0)
  })

  test('link de convite que não existe', async ({ page, goto }) => {
    await page.route('**/api/v1/invitations/nada', (route) =>
      route.fulfill({ status: 404, json: { code: 'not_found', message: 'Recurso não encontrado.', errors: null, request_id: 'r' } }))

    await goto('/convite/nada', { waitUntil: 'hydration' })

    await expect(page.getByRole('heading', { name: 'Convite não encontrado' })).toBeVisible()
  })
})
