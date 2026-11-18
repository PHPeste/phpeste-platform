import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { ThemeError } from './themeError'
import type { LogoSlot } from '../types'

interface ThemePayload {
  colors: Record<string, string>
  fonts: { heading: string, body: string }
  radius: number
  logos: Record<LogoSlot, string | null>
}

export interface ThemeSettingsPayload {
  data: {
    edition_id: string
    draft: ThemePayload
    published: ThemePayload | null
    published_at: string | null
    has_unpublished_changes: boolean
    can_revert: boolean
  }
}

export interface PresetsPayload {
  data: Array<{ id: string, label: string, description: string, theme: { colors: Record<string, string> } }>
  meta: {
    tokens: Array<{ id: string, label: string }>
    contrast_pairs: Array<{ foreground: string, background: string, minimum: number }>
    fonts: string[]
    radius: { min: number, max: number }
  }
}

const themeUrl = (editionId: string) => `/editions/${editionId}/theme`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new ThemeError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new ThemeError('Request failed')
  }
}

export default {
  get: (editionId: string) => call(() => httpClient.get<ThemeSettingsPayload>(themeUrl(editionId))),

  presets: () => call(() => httpClient.get<PresetsPayload>('/branding/presets')),

  saveDraft: (editionId: string, theme: { colors: Record<string, string>, fonts: { heading: string, body: string }, radius: number }) =>
    call(() => httpClient.put<ThemeSettingsPayload>(`${themeUrl(editionId)}/draft`, { body: theme })),

  applyPreset: (editionId: string, preset: string) =>
    call(() => httpClient.post<ThemeSettingsPayload>(`${themeUrl(editionId)}/draft/preset`, { body: { preset } })),

  discard: (editionId: string) => call(() => httpClient.delete<ThemeSettingsPayload>(`${themeUrl(editionId)}/draft`)),

  publish: (editionId: string) => call(() => httpClient.post<ThemeSettingsPayload>(`${themeUrl(editionId)}/publish`)),

  revert: (editionId: string) => call(() => httpClient.post<ThemeSettingsPayload>(`${themeUrl(editionId)}/revert`)),

  uploadLogo: (editionId: string, slot: LogoSlot, file: File) => {
    const body = new FormData()
    body.append('file', file)
    return call(() => httpClient.post<ThemeSettingsPayload>(`${themeUrl(editionId)}/logos/${slot}`, { body }))
  },

  removeLogo: (editionId: string, slot: LogoSlot) => call(() => httpClient.delete<ThemeSettingsPayload>(`${themeUrl(editionId)}/logos/${slot}`)),
}
