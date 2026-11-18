import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '@/stores/authStore'
import githubLoginService from '../services/githubLoginService'
import { GithubLoginError } from '../services/githubLoginError'
import { useGithubCallback } from './useGithubCallback'

vi.mock('../services/githubLoginService', () => ({ default: { callback: vi.fn() } }))

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useGithubCallback', () => {
  it('saves the session returned by the backend', async () => {
    vi.mocked(githubLoginService.callback).mockResolvedValueOnce({
      user: { id: 'user-1', name: 'Ada', email: 'ada@example.com', avatar_url: null, created_at: '2026-01-01' },
      token: 'gh-token',
    })

    expect(await useGithubCallback().submit({ code: 'c', state: 's' })).toBe(true)
    expect(useAuthStore().token).toBe('gh-token')
  })

  it('explains an expired state and shows the backend reason for other refusals', async () => {
    vi.mocked(githubLoginService.callback).mockRejectedValueOnce(new GithubLoginError('x', 422, { state: ['inválido'] }))
    const expired = useGithubCallback()
    await expired.submit({ code: 'c', state: 's' })
    expect(expired.errorMessage.value).toBe('A tentativa de entrar com GitHub expirou ou é inválida. Comece de novo.')

    vi.mocked(githubLoginService.callback).mockRejectedValueOnce(new GithubLoginError('x', 422, { github: ['Sua conta do GitHub não tem um e-mail principal verificado.'] }))
    const noEmail = useGithubCallback()
    await noEmail.submit({ code: 'c', state: 's' })
    expect(noEmail.errorMessage.value).toBe('Sua conta do GitHub não tem um e-mail principal verificado.')
  })
})
