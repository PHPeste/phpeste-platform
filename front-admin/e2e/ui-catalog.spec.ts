import { expect, test } from '@nuxt/test-utils/playwright'

// Comportamento de teclado e foco do kit de UI, no navegador de verdade,
// usando a página de catálogo (/_dev/componentes, só existe em dev).
test.describe('Catálogo de componentes (teclado e foco)', () => {
  test.beforeEach(async ({ goto }) => {
    await goto('/_dev/componentes', { waitUntil: 'hydration' })
  })

  test('o foco visível aparece ao navegar com Tab', async ({ page }) => {
    await page.keyboard.press('Tab')
    const focused = page.locator(':focus-visible')
    await expect(focused).toBeVisible()
    const outline = await focused.evaluate((el) => getComputedStyle(el).outlineStyle)
    expect(outline).not.toBe('none')
  })

  test('abas: setas, Home e End trocam a aba ativa', async ({ page }) => {
    const tablist = page.getByRole('tablist', { name: 'Seções do CFP' })
    const tabs = tablist.getByRole('tab')
    await tabs.first().focus()
    await page.keyboard.press('ArrowRight')
    await expect(tabs.nth(1)).toHaveAttribute('aria-selected', 'true')
    await expect(tabs.nth(1)).toBeFocused()
    await page.keyboard.press('End')
    await expect(tabs.last()).toHaveAttribute('aria-selected', 'true')
    await page.keyboard.press('Home')
    await expect(tabs.first()).toHaveAttribute('aria-selected', 'true')
  })

  test('modal: abre, prende o foco e fecha com Esc devolvendo o foco', async ({ page }) => {
    const opener = page.getByRole('button', { name: 'Abrir modal' })
    await opener.click()
    const dialog = page.getByRole('dialog', { name: 'Reembolsar pedido #1037' })
    await expect(dialog).toBeVisible()
    // Com <dialog> modal o conteúdo de fora fica inerte: o foco nunca chega nele.
    const background = page.getByRole('button', { name: 'Abrir painel lateral' })
    for (let i = 0; i < 8; i++) {
      await page.keyboard.press('Tab')
      await expect(background).not.toBeFocused()
    }
    await page.keyboard.press('Escape')
    await expect(dialog).toBeHidden()
    await expect(opener).toBeFocused()
  })

  test('painel lateral: abre e fecha pelo botão Cancelar', async ({ page }) => {
    await page.getByRole('button', { name: 'Abrir painel lateral' }).click()
    const drawer = page.getByRole('dialog', { name: 'Editar sessão' })
    await expect(drawer).toBeVisible()
    await drawer.getByRole('button', { name: 'Cancelar' }).click()
    await expect(drawer).toBeHidden()
  })

  test('aviso: aparece numa região viva, tem ação e pode ser dispensado', async ({ page }) => {
    await page.getByRole('button', { name: 'Aviso de sucesso' }).click()
    const region = page.getByRole('region', { name: 'Avisos', exact: true })
    await expect(region).toContainText('Identidade publicada.')
    await region.getByRole('button', { name: 'Desfazer' }).focus()
    await page.keyboard.press('Enter')
    await expect(region).not.toContainText('Identidade publicada.')
  })

  test('tabela larga é rolável e alcançável pelo teclado', async ({ page }) => {
    const region = page.getByRole('region', { name: 'Pedidos recentes' })
    await expect(region).toHaveAttribute('tabindex', '0')
  })
})
