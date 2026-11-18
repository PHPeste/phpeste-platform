import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import forgotPasswordService from './forgotPasswordService'
import { ForgotPasswordError } from './forgotPasswordError'

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

describe('forgotPasswordService', () => {
  it('posts the e-mail to /auth/password/forgot', async () => {
    vi.mocked(httpClient.post).mockResolvedValueOnce({ sent: true, message: 'ok' })

    await forgotPasswordService.requestLink({ email: 'ada@example.com' })

    expect(httpClient.post).toHaveBeenCalledWith('/auth/password/forgot', { body: { email: 'ada@example.com' } })
  })

  it('wraps failures keeping the status', async () => {
    vi.mocked(httpClient.post).mockRejectedValueOnce(new HttpClientError('Too many', 429))

    const promise = forgotPasswordService.requestLink({ email: 'ada@example.com' })
    await expect(promise).rejects.toBeInstanceOf(ForgotPasswordError)
    await expect(promise).rejects.toMatchObject({ statusCode: 429 })
  })
})
