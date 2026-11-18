import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import cfpSettingsService from './cfpSettingsService'
import { CfpSettingsError } from './cfpSettingsError'

vi.mock('@/infrastructure/http/HttpClient', () => ({
  httpClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  HttpClientError: class extends Error {
    statusCode: number | null
    fieldErrors: Record<string, string[]> | null

    constructor(message: string, statusCode: number | null = null, fieldErrors: Record<string, string[]> | null = null) {
      super(message)
      this.statusCode = statusCode
      this.fieldErrors = fieldErrors
    }
  },
}))

describe('cfpSettingsService', () => {
  it('chama as rotas de configuração, trilhas e critérios da edição', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: [] })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.patch).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.delete).mockResolvedValue(null)

    await cfpSettingsService.settings('ed-1')
    await cfpSettingsService.tracks('ed-1')
    await cfpSettingsService.criteria('ed-1')
    await cfpSettingsService.createTrack('ed-1', { name: 'Backend', slots: 6 })
    await cfpSettingsService.updateTrack('ed-1', 't-1', { position: 2 })
    await cfpSettingsService.deleteTrack('ed-1', 't-1')
    await cfpSettingsService.updateCriterion('ed-1', 'c-1', { weight: 3, confirm: true })
    await cfpSettingsService.deleteCriterion('ed-1', 'c-1', true)

    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/cfp/settings')
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/cfp/tracks')
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/cfp/criteria')
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/cfp/tracks', { body: { name: 'Backend', slots: 6 } })
    expect(httpClient.patch).toHaveBeenCalledWith('/editions/ed-1/cfp/tracks/t-1', { body: { position: 2 } })
    expect(httpClient.delete).toHaveBeenCalledWith('/editions/ed-1/cfp/tracks/t-1')
    expect(httpClient.patch).toHaveBeenCalledWith('/editions/ed-1/cfp/criteria/c-1', { body: { weight: 3, confirm: true } })
    expect(httpClient.delete).toHaveBeenCalledWith('/editions/ed-1/cfp/criteria/c-1', { body: { confirm: true } })
  })

  it('embrulha o erro da API num CfpSettingsError', async () => {
    vi.mocked(httpClient.patch).mockRejectedValueOnce(new HttpClientError('x', 422, { closes_at: ['O fechamento precisa ser depois da abertura.'] }))

    const error = await cfpSettingsService.updateSettings('ed-1', {} as never).catch((e: unknown) => e)
    expect(error).toBeInstanceOf(CfpSettingsError)
    expect(error).toMatchObject({ statusCode: 422 })
  })
})
