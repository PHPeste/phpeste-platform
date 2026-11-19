// Converte arquitetura.html em ../PHPeste_Arquitetura.pdf usando o Chromium
// do Playwright que já vem com o front-admin/.
//
// Uso (na raiz do repo):
//   python3 docs/arquitetura/fonte/gerar.py && node docs/arquitetura/fonte/imprimir.mjs
// Com uma pasta no fim, salva também um PNG de cada página:
//   node docs/arquitetura/fonte/imprimir.mjs /tmp/paginas
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const require = createRequire(join(here, '../../../front-admin/package.json'))
const { chromium } = require('playwright-core')

const html = join(here, 'arquitetura.html')
const pdf = join(here, '..', 'PHPeste_Arquitetura.pdf')
const shot = process.argv[2]

const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1123, height: 794 } })
await page.goto(pathToFileURL(html).href, { waitUntil: 'networkidle' })
await page.pdf({ path: pdf, format: 'A4', landscape: true, printBackground: true, preferCSSPageSize: true })

if (shot) {
  const pages = await page.locator('section.page').all()
  for (const [i, el] of pages.entries()) {
    await el.screenshot({ path: join(shot, `pagina-${String(i + 1).padStart(2, '0')}.png`) })
  }
}

await browser.close()
console.log(`ok: ${pdf}`)
