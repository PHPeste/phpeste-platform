import { describe, expect, it } from 'vitest'
import { fontHref, isAreaPath, normalizeHost, route, safeUrl, themeCss, type ResolvedEdition } from './index'

const edition = (overrides: Partial<ResolvedEdition> = {}): ResolvedEdition => ({
  id: 'ed-1', name: 'PHPeste 2027', host: 'cfp.phpeste.dev', primaryHost: 'cfp.phpeste.dev', siteMode: 'platform', externalSiteUrl: null, theme: null, ...overrides,
})

describe('normalizeHost', () => {
  it('normaliza o host da requisição e recusa valores estranhos', () => {
    expect(normalizeHost('CFP.PHPeste.dev:443')).toBe('cfp.phpeste.dev')
    expect(normalizeHost('a.dev, b.dev')).toBe('a.dev')
    expect(normalizeHost('evento.dev.')).toBe('evento.dev')
    expect(normalizeHost('localhost:3000')).toBe('localhost')
    expect(normalizeHost('<script>.dev')).toBeNull()
    expect(normalizeHost('semponto')).toBeNull()
    expect(normalizeHost(undefined)).toBeNull()
  })
})

describe('route', () => {
  it('manda o domínio secundário para o principal, mantendo o caminho', () => {
    expect(route(edition({ host: 'antigo.dev' }), '/cfp?x=1')).toEqual({ kind: 'redirect', location: 'https://cfp.phpeste.dev/cfp?x=1', status: 301 })
  })

  it('no site próprio, só a área do palestrante e participante fica na plataforma', () => {
    const external = edition({ siteMode: 'external', externalSiteUrl: 'https://phpeste.php-pb.net' })
    expect(route(external, '/')).toEqual({ kind: 'redirect', location: 'https://phpeste.php-pb.net', status: 302 })
    expect(route(external, '/programacao')).toMatchObject({ kind: 'redirect' })
    expect(route(external, '/cfp')).toEqual({ kind: 'render' })
    expect(route(external, '/palestrante/propostas/abc')).toEqual({ kind: 'render' })
  })

  it('no site da plataforma, renderiza tudo', () => {
    expect(route(edition(), '/')).toEqual({ kind: 'render' })
    expect(isAreaPath('/cfpx')).toBe(false)
    expect(isAreaPath('/cfp/')).toBe(true)
  })
})

describe('themeCss', () => {
  it('transforma o tema publicado em variáveis --ph-*', () => {
    const css = themeCss({ colors: { brand: '#2C78CA', surface_2: '#1D3250', support: '#B4532A' }, fonts: { heading: 'Inter', body: 'Inter' }, radius: 6, logos: { primary: null, dark: null, favicon: null, app_icon: null } })

    expect(css).toBe(":root{--ph-brand:#2c78ca;--ph-surface-2:#1d3250;--ph-terra:#b4532a;--ph-font-heading:'Inter',sans-serif;--ph-font-body:'Inter',sans-serif;--ph-radius:6px}")
  })

  it('ignora valores que poderiam quebrar o CSS ou injetar conteúdo', () => {
    const css = themeCss({ colors: { brand: 'red;}</style><script>', 'bad key': '#000000', ink: '#3A1607' }, fonts: { heading: "x';}", body: 'Lexend' }, radius: 999, logos: { primary: null, dark: null, favicon: null, app_icon: null } })

    expect(css).toBe(":root{--ph-ink:#3a1607;--ph-font-body:'Lexend',sans-serif}")
    expect(themeCss(null)).toBe('')
  })
})

describe('fontHref e safeUrl', () => {
  it('monta o link do Google Fonts sem repetir família', () => {
    expect(fontHref({ colors: {}, fonts: { heading: 'Space Grotesk', body: 'Space Grotesk' }, radius: 8, logos: { primary: null, dark: null, favicon: null, app_icon: null } }))
      .toBe('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap')
  })

  it('só aceita https sem caracteres de marcação', () => {
    expect(safeUrl('https://cdn.dev/logo.png')).toBe('https://cdn.dev/logo.png')
    expect(safeUrl('javascript:alert(1)')).toBeNull()
    expect(safeUrl('https://x.dev/"><script>')).toBeNull()
  })
})
