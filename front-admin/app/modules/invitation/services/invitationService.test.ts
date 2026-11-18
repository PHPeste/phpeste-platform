import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import invitationService from './invitationService'

vi.mock('@/infrastructure/http/HttpClient', () => ({
  httpClient: { post: vi.fn(), get: vi.fn() },
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

describe('invitationService', () => {
  it('loads the invitation by token', async () => {
    vi.mocked(httpClient.get).mockResolvedValueOnce({ data: {} })

    await invitationService.show('abc/123')

    expect(httpClient.get).toHaveBeenCalledWith('/invitations/abc%2F123')
  })

  it('accepts with name and password', async () => {
    vi.mocked(httpClient.post).mockResolvedValueOnce({ user: {}, edition_id: 'e1', token: 't' })

    await invitationService.accept({ token: 'abc', name: 'Lívia', password: 'senha123456', passwordConfirmation: 'senha123456' })

    expect(httpClient.post).toHaveBeenCalledWith('/invitations/abc/accept', {
      body: { name: 'Lívia', password: 'senha123456', password_confirmation: 'senha123456', device_name: 'Painel PHPeste' },
    })
  })

  it('keeps the 410 status of a used or expired invitation', async () => {
    vi.mocked(httpClient.get).mockRejectedValueOnce(new HttpClientError('Este convite expirou.', 410))

    await expect(invitationService.show('abc')).rejects.toMatchObject({ statusCode: 410, message: 'Este convite expirou.' })
  })
})
