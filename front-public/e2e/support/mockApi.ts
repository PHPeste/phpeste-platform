import { createServer, type Server } from 'node:http'

export const MOCK_API_PORT = 8799

const sertao = { bg: '#FBF4E4', surface: '#FFFBF2', ink: '#3A1607', brand: '#6B2A12', on_brand: '#FFF8E8', accent: '#E8B544', support: '#B4532A' }
const theme = (colors: Record<string, string>, font = 'Lexend', logo: string | null = null) => ({ colors: { ...sertao, ...colors }, fonts: { heading: font, body: font }, radius: 8, logos: { primary: logo, dark: null, favicon: null, app_icon: null } })

const editions: Record<string, { id: string, name: string, primary: string, siteMode: string, external: string | null, theme: unknown }> = {
  'localhost': { id: 'ed-a', name: 'PHPeste 2027', primary: 'localhost', siteMode: 'platform', external: null, theme: theme({ bg: '#0F1B2D', brand: '#2C78CA' }, 'Inter') },
  'b.phpce.test': { id: 'ed-b', name: 'PHP Ceará 2027', primary: 'b.phpce.test', siteMode: 'platform', external: null, theme: theme({ bg: '#12100E', brand: '#FF7A1A' }) },
  'antigo.phpce.test': { id: 'ed-b', name: 'PHP Ceará 2027', primary: 'b.phpce.test', siteMode: 'platform', external: null, theme: null },
  'cfp.php-pb.test': { id: 'ed-c', name: 'PHP Paraíba 2027', primary: 'cfp.php-pb.test', siteMode: 'external', external: 'https://phpeste.php-pb.net', theme: theme({}) },
}

export function startMockApi(): Promise<Server> {
  const server = createServer((request, response) => {
    const url = new URL(request.url ?? '/', `http://127.0.0.1:${MOCK_API_PORT}`)
    const send = (status: number, body: unknown) => {
      response.writeHead(status, { 'Content-Type': 'application/json' })
      response.end(JSON.stringify(body))
    }

    if (url.pathname === '/api/v1/public/resolve') {
      const host = url.searchParams.get('host') ?? ''
      const local = host === '127.0.0.1' || host === 'localhost'
      const edition = local ? editions.localhost : editions[host]
      if (!edition) return send(404, { code: 'not_found', message: 'Nenhuma edição publicada neste domínio.' })
      return send(200, { data: { edition_id: edition.id, edition_name: edition.name, host, primary_host: local ? host : edition.primary, site_mode: edition.siteMode, external_site_url: edition.external } })
    }

    const themeMatch = url.pathname.match(/^\/api\/v1\/public\/editions\/([^/]+)\/theme$/)
    if (themeMatch) {
      const edition = Object.values(editions).find((item) => item.id === themeMatch[1] && item.theme)
      return edition ? send(200, { data: { edition_id: edition.id, theme: edition.theme } }) : send(404, {})
    }

    return send(404, {})
  })

  return new Promise((resolve) => server.listen(MOCK_API_PORT, '127.0.0.1', () => resolve(server)))
}
