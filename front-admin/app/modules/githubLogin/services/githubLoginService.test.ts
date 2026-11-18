import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import githubLoginService from './githubLoginService'
import { GithubLoginError } from './githubLoginError'

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

describe('githubLoginService', () => {
  it('posts code and state to the callback', async () => {
    vi.mocked(httpClient.post).mockResolvedValueOnce({ user: {}, token: 't' })

    await githubLoginService.callback({ code: 'c0de', state: 'st4te' })

    expect(httpClient.post).toHaveBeenCalledWith('/auth/github/callback', {
      body: { code: 'c0de', state: 'st4te', device_name: 'Painel PHPeste' },
    })
  })

  it('wraps failures into a GithubLoginError', async () => {
    vi.mocked(httpClient.post).mockRejectedValueOnce(new HttpClientError('x', 422, { state: ['expirou'] }))

    await expect(githubLoginService.callback({ code: 'c', state: 's' })).rejects.toBeInstanceOf(GithubLoginError)
  })
})
