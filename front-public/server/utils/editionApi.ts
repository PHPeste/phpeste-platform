import type { H3Event } from 'h3'
import type { EditionTheme, ResolvedEdition, SiteMode } from '#shared/edition'

const CACHE_SECONDS = Number(process.env.NUXT_EDITION_CACHE_SECONDS || 60)

interface ResolvePayload {
  data: { edition_id: string, edition_name: string, host: string, primary_host: string, site_mode?: SiteMode, external_site_url?: string | null }
}

interface ThemePayload {
  data: { theme: EditionTheme }
}

const fetchResolved = defineCachedFunction(async (_event: H3Event, host: string): Promise<ResolvePayload['data'] | null> => {
  const { apiInternalUrl } = useRuntimeConfig()
  try {
    const payload = await $fetch<ResolvePayload>(`${apiInternalUrl}/public/resolve`, { query: { host }, headers: { Accept: 'application/json' }, timeout: 3000 })
    return payload.data
  } catch (error) {
    const status = (error as { statusCode?: number, response?: { status?: number } }).response?.status ?? (error as { statusCode?: number }).statusCode
    if (status === 404) return null
    throw error
  }
}, { name: 'edition-resolve', maxAge: CACHE_SECONDS, swr: true, getKey: (_event: H3Event, host: string) => host })

const fetchTheme = defineCachedFunction(async (_event: H3Event, editionId: string): Promise<EditionTheme | null> => {
  const { apiInternalUrl } = useRuntimeConfig()
  try {
    const payload = await $fetch<ThemePayload>(`${apiInternalUrl}/public/editions/${editionId}/theme`, { headers: { Accept: 'application/json' }, timeout: 3000 })
    return payload.data.theme
  } catch {
    return null
  }
}, { name: 'edition-theme', maxAge: CACHE_SECONDS, swr: true, getKey: (_event: H3Event, editionId: string) => editionId })

export async function resolveEdition(event: H3Event, host: string): Promise<ResolvedEdition | null> {
  const resolved = await fetchResolved(event, host)
  if (!resolved) return null

  return {
    id: resolved.edition_id,
    name: resolved.edition_name,
    host: resolved.host,
    primaryHost: resolved.primary_host,
    siteMode: resolved.site_mode ?? 'platform',
    externalSiteUrl: resolved.external_site_url ?? null,
    theme: await fetchTheme(event, resolved.edition_id),
  }
}
