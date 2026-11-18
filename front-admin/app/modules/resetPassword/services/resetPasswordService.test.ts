import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import resetPasswordService from './resetPasswordService'

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

describe('resetPasswordService', () => {
  it('sends token, e-mail and the new password with its confirmation', async () => {
    vi.mocked(httpClient.post).mockResolvedValueOnce({ reset: true })

    await resetPasswordService.reset({ email: 'ada@example.com', token: 'tok', password: 'senhanova123', passwordConfirmation: 'senhanova123' })

    expect(httpClient.post).toHaveBeenCalledWith('/auth/password/reset', {
      body: { email: 'ada@example.com', token: 'tok', password: 'senhanova123', password_confirmation: 'senhanova123' },
    })
  })

  it('keeps field errors from the API', async () => {
    vi.mocked(httpClient.post).mockRejectedValueOnce(new HttpClientError('invalid', 422, { token: ['expirou'] }))

    await expect(resetPasswordService.reset({ email: 'a@a.com', token: 't', password: 'x', passwordConfirmation: 'x' }))
      .rejects.toMatchObject({ statusCode: 422, fieldErrors: { token: ['expirou'] } })
  })
})
