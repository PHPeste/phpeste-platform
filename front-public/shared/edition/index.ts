export type SiteMode = 'platform' | 'external'

export interface EditionTheme {
  colors: Record<string, string>
  fonts: { heading: string, body: string }
  radius: number
  logos: { primary: string | null, dark: string | null, favicon: string | null, app_icon: string | null }
}

export interface ResolvedEdition {
  id: string
  name: string
  host: string
  primaryHost: string
  siteMode: SiteMode
  externalSiteUrl: string | null
  theme: EditionTheme | null
}

export const AREA_PREFIXES = ['/cfp', '/palestrante', '/entrar', '/login', '/conta', '/ingressos', '/pedidos', '/checkout', '/redefinir-senha']

const TOKEN_NAMES: Record<string, string> = { support: 'terra' }
const COLOR = /^#[0-9a-fA-F]{6}$/
const FONT = /^[A-Za-z0-9 ]{1,40}$/
const URL_SAFE = /^https:\/\/[^\s"'<>()]+$/

export function normalizeHost(value: string | null | undefined): string | null {
  if (!value) return null
  const host = value.split(',')[0]!.trim().toLowerCase().replace(/:\d+$/, '').replace(/\.$/, '')
  return /^[a-z0-9.-]{1,253}$/.test(host) && host.includes('.') || host === 'localhost' ? host : null
}

export function isAreaPath(path: string): boolean {
  const clean = path.split('?')[0]!.replace(/\/+$/, '') || '/'
  return AREA_PREFIXES.some((prefix) => clean === prefix || clean.startsWith(`${prefix}/`))
}

export type Routing = { kind: 'render' } | { kind: 'redirect', location: string, status: 301 | 302 }

export function route(edition: ResolvedEdition, path: string): Routing {
  if (edition.host !== edition.primaryHost) {
    return { kind: 'redirect', location: `https://${edition.primaryHost}${path}`, status: 301 }
  }
  if (edition.siteMode === 'external' && edition.externalSiteUrl && !isAreaPath(path)) {
    return { kind: 'redirect', location: edition.externalSiteUrl, status: 302 }
  }
  return { kind: 'render' }
}

export function themeCss(theme: EditionTheme | null): string {
  if (!theme) return ''
  const vars: string[] = []
  for (const [token, value] of Object.entries(theme.colors)) {
    if (!COLOR.test(value) || !/^[a-z0-9_]+$/.test(token)) continue
    vars.push(`--ph-${(TOKEN_NAMES[token] ?? token).replace(/_/g, '-')}:${value.toLowerCase()}`)
  }
  if (FONT.test(theme.fonts.heading)) vars.push(`--ph-font-heading:'${theme.fonts.heading}',sans-serif`)
  if (FONT.test(theme.fonts.body)) vars.push(`--ph-font-body:'${theme.fonts.body}',sans-serif`)
  if (Number.isFinite(theme.radius) && theme.radius >= 0 && theme.radius <= 48) vars.push(`--ph-radius:${Math.round(theme.radius)}px`)
  return vars.length ? `:root{${vars.join(';')}}` : ''
}

export function fontHref(theme: EditionTheme | null): string | null {
  if (!theme) return null
  const families = [...new Set([theme.fonts.heading, theme.fonts.body])].filter((font) => FONT.test(font))
  if (!families.length) return null
  return `https://fonts.googleapis.com/css2?${families.map((font) => `family=${font.replace(/ /g, '+')}:wght@400;500;600`).join('&')}&display=swap`
}

export function safeUrl(value: string | null | undefined): string | null {
  return value && URL_SAFE.test(value) ? value : null
}
