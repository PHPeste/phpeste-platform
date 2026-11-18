import { expect, test } from '@nuxt/test-utils/playwright'

const host = (value: string) => ({ 'X-Forwarded-Host': value })

test.describe('Edição pelo endereço', () => {
  test('dois endereços mostram duas identidades, já na primeira renderização', async ({ page, goto, request }) => {
    const first = await request.get('/', { headers: host('localhost') })
    const second = await request.get('/', { headers: host('b.phpce.test') })

    expect(first.status()).toBe(200)
    expect(await first.text()).toContain('--ph-brand:#2c78ca')
    expect(await second.text()).toContain('--ph-brand:#ff7a1a')
    expect(await second.text()).toContain('PHP Ceará 2027')

    await goto('/', { waitUntil: 'hydration' })
    await expect(page.getByRole('heading', { name: 'PHPeste 2027', level: 1 })).toBeVisible()
    expect(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--ph-brand').trim())).toBe('#2c78ca')
    expect(await page.evaluate(() => getComputedStyle(document.body).fontFamily)).toContain('Inter')
    await expect(page).toHaveTitle('PHPeste 2027')
  })

  test('página só do navegador também nasce com o tema da edição', async ({ request }) => {
    const response = await request.get('/palestrante/propostas', { headers: host('b.phpce.test') })
    const html = await response.text()

    expect(html).toContain('id="edition-theme"')
    expect(html).toContain('--ph-bg:#12100e')
    expect(html).toContain('"name":"PHP Ceará 2027"')
  })

  test('domínio que não é o principal redireciona para o principal mantendo o caminho', async ({ request }) => {
    const response = await request.get('/palestrante/propostas?aba=1', { headers: host('antigo.phpce.test'), maxRedirects: 0 })

    expect(response.status()).toBe(301)
    expect(response.headers().location).toBe('https://b.phpce.test/palestrante/propostas?aba=1')
  })

  test('no site próprio, a raiz vai para o site da comunidade e a área do palestrante fica aqui', async ({ request }) => {
    const root = await request.get('/', { headers: host('cfp.php-pb.test'), maxRedirects: 0 })
    expect(root.status()).toBe(302)
    expect(root.headers().location).toBe('https://phpeste.php-pb.net')

    const area = await request.get('/palestrante/propostas', { headers: host('cfp.php-pb.test'), maxRedirects: 0 })
    expect(area.status()).toBe(200)
    expect(await area.text()).toContain('"siteMode":"external"')
  })

  test('endereço desconhecido mostra uma página neutra, sem citar nenhuma edição', async ({ page, goto, request }) => {
    const response = await request.get('/', { headers: host('desconhecido.test') })
    const html = await response.text()

    expect(response.status()).toBe(404)
    expect(html).toContain('Este endereço não tem uma edição publicada')
    expect(html).not.toContain('PHPeste 2027')
    expect(html).not.toContain('PHP Ceará')

    await page.setExtraHTTPHeaders(host('desconhecido.test'))
    await goto('/', { waitUntil: 'hydration' })
    await expect(page.getByTestId('unknown-site')).toBeVisible()
  })

  test('o cabeçalho leva de volta ao site da edição', async ({ page, goto }) => {
    await goto('/', { waitUntil: 'hydration' })
    await expect(page.getByTestId('back-to-site')).toHaveAttribute('href', /^https:\/\/(127\.0\.0\.1|localhost)$/)
    await expect(page.getByTestId('site-brand')).toContainText('PHPeste 2027')
  })
})
