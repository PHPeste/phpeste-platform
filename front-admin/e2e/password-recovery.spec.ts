import { expect, test } from '@nuxt/test-utils/playwright'

test.describe('Recuperação de senha', () => {
  test('pede o link e mostra a mesma confirmação, sem dizer se o e-mail existe', async ({ page, goto }) => {
    await page.route('**/api/v1/auth/password/forgot', async (route) => {
      expect(route.request().postDataJSON()).toEqual({ email: 'ninguem@example.com' })
      await route.fulfill({ status: 200, json: { sent: true, message: 'Se existir uma conta com este e-mail, enviamos um link.' } })
    })

    await goto('/esqueci-senha', { waitUntil: 'hydration' })
    await page.getByRole('button', { name: 'Enviar link' }).click()
    await expect(page.getByRole('alert')).toHaveText('Informe o e-mail.')

    await page.getByLabel('E-mail').fill('ninguem@example.com')
    await page.getByRole('button', { name: 'Enviar link' }).click()

    await expect(page.getByRole('status').filter({ hasText: 'Se existir uma conta com este e-mail, enviamos um link para redefinir a senha.' })).toBeVisible()
  })

  test('troca a senha pelo link e manda entrar de novo', async ({ page, goto }) => {
    await page.route('**/api/v1/auth/password/reset', async (route) => {
      expect(route.request().postDataJSON()).toEqual({ email: 'ada@example.com', token: 'tok123', password: 'senhanova123', password_confirmation: 'senhanova123' })
      await route.fulfill({ status: 200, json: { reset: true } })
    })

    await goto('/redefinir-senha?token=tok123&email=ada%40example.com', { waitUntil: 'hydration' })
    await page.getByLabel('Nova senha', { exact: true }).fill('senhanova123')
    await page.getByLabel('Repita a nova senha').fill('outra-senha')
    await page.getByRole('button', { name: 'Salvar nova senha' }).click()
    await expect(page.getByText('As senhas não são iguais.')).toBeVisible()

    await page.getByLabel('Repita a nova senha').fill('senhanova123')
    await page.getByRole('button', { name: 'Salvar nova senha' }).click()

    await expect(page.getByRole('status').filter({ hasText: 'Senha alterada' })).toBeVisible()
    await page.getByRole('link', { name: 'Ir para a entrada' }).click()
    await expect(page).toHaveURL(/\/login$/)
  })

  test('avisa quando o link expirou e oferece pedir outro', async ({ page, goto }) => {
    await page.route('**/api/v1/auth/password/reset', (route) =>
      route.fulfill({
        status: 422,
        json: { code: 'validation_error', message: 'Os dados enviados são inválidos.', errors: { token: ['Este link de redefinição é inválido ou expirou. Peça um novo.'] }, request_id: 'r' },
      }))

    await goto('/redefinir-senha?token=velho&email=ada%40example.com', { waitUntil: 'hydration' })
    await page.getByLabel('Nova senha', { exact: true }).fill('senhanova123')
    await page.getByLabel('Repita a nova senha').fill('senhanova123')
    await page.getByRole('button', { name: 'Salvar nova senha' }).click()

    await expect(page.getByRole('alert')).toContainText('Este link de redefinição é inválido ou expirou.')
    await expect(page.getByRole('link', { name: 'Pedir um novo link' })).toHaveAttribute('href', '/esqueci-senha')
  })
})
