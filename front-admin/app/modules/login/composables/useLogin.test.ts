import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '@/stores/authStore'
import loginService from '../services/loginService'
import { LoginError } from '../services/loginError'
import { useGithubLogin, useLogin } from './useLogin'

vi.mock('../services/loginService', () => ({
  default: { login: vi.fn(), githubRedirect: vi.fn() },
}))

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useLogin', () => {
  it('starts with a clean state', () => {
    const { isSubmitting, errorMessage, fieldErrors } = useLogin()

    expect(isSubmitting.value).toBe(false)
    expect(errorMessage.value).toBeNull()
    expect(fieldErrors.value).toBeNull()
  })

  it('on success, saves the session in authStore and resolves true', async () => {
    vi.mocked(loginService.login).mockResolvedValueOnce({
      user: { id: 'user-1', name: 'Ada Lovelace', email: 'ada@example.com', avatar_url: null, created_at: '2026-01-01' },
      token: 'abc123',
    })

    const { submit, isSubmitting, errorMessage } = useLogin()
    const result = await submit({ email: 'ada@example.com', password: 'password', remember: true })

    expect(result).toBe(true)
    expect(isSubmitting.value).toBe(false)
    expect(errorMessage.value).toBeNull()

    const authStore = useAuthStore()
    expect(authStore.token).toBe('abc123')
    expect(authStore.user).toEqual({ id: 'user-1', name: 'Ada Lovelace', email: 'ada@example.com', avatarUrl: null })
  })

  it('maps a 401 to the invalid credentials message', async () => {
    vi.mocked(loginService.login).mockRejectedValueOnce(new LoginError('bad creds', 401))

    const { submit, errorMessage } = useLogin()
    const result = await submit({ email: 'x@x.com', password: 'wrong', remember: true })

    expect(result).toBe(false)
    expect(errorMessage.value).toBe('E-mail ou senha inválidos.')
  })

  it('maps a 429 to the rate limit message', async () => {
    vi.mocked(loginService.login).mockRejectedValueOnce(new LoginError('too many', 429))

    const { submit, errorMessage } = useLogin()
    await submit({ email: 'x@x.com', password: 'y', remember: true })

    expect(errorMessage.value).toBe('Muitas tentativas seguidas. Espere um pouco e tente de novo.')
  })

  it('maps a 422 to the first field error message', async () => {
    vi.mocked(loginService.login).mockRejectedValueOnce(
      new LoginError('validation failed', 422, { email: ['O campo email é obrigatório.'] }),
    )

    const { submit, errorMessage, fieldErrors } = useLogin()
    await submit({ email: '', password: 'y', remember: true })

    expect(errorMessage.value).toBe('O campo email é obrigatório.')
    expect(fieldErrors.value).toEqual({ email: ['O campo email é obrigatório.'] })
  })

  it('falls back to a generic message for unexpected errors', async () => {
    vi.mocked(loginService.login).mockRejectedValueOnce(new Error('boom'))

    const { submit, errorMessage } = useLogin()
    await submit({ email: 'x@x.com', password: 'y', remember: true })

    expect(errorMessage.value).toBe('Não deu para entrar agora. Tente de novo.')
  })

  it('sets isSubmitting while the request is pending', async () => {
    let resolveLogin!: (value: Awaited<ReturnType<typeof loginService.login>>) => void
    vi.mocked(loginService.login).mockReturnValueOnce(new Promise((resolve) => { resolveLogin = resolve }))

    const { submit, isSubmitting } = useLogin()
    const pending = submit({ email: 'x@x.com', password: 'y', remember: true })

    expect(isSubmitting.value).toBe(true)

    resolveLogin({
      user: { id: 'user-1', name: 'Ada Lovelace', email: 'ada@example.com', avatar_url: null, created_at: '2026-01-01' },
      token: 'abc123',
    })
    await pending

    expect(isSubmitting.value).toBe(false)
  })
})

describe('useLogin: manter conectado', () => {
  const payload = {
    user: { id: 'user-1', name: 'Ada Lovelace', email: 'ada@example.com', avatar_url: null, created_at: '2026-01-01' },
    token: 'abc123',
  }

  it('keeps the session in localStorage when remember is on', async () => {
    vi.mocked(loginService.login).mockResolvedValueOnce(payload)

    await useLogin().submit({ email: 'ada@example.com', password: 'password', remember: true })

    expect(localStorage.getItem('app.auth')).toContain('abc123')
    expect(sessionStorage.getItem('app.auth')).toBeNull()
  })

  it('keeps the session only for this browser session when remember is off', async () => {
    vi.mocked(loginService.login).mockResolvedValueOnce(payload)

    await useLogin().submit({ email: 'ada@example.com', password: 'password', remember: false })

    expect(sessionStorage.getItem('app.auth')).toContain('abc123')
    expect(localStorage.getItem('app.auth')).toBeNull()
  })
})

describe('useGithubLogin', () => {
  it('sends the browser to the GitHub authorization URL', async () => {
    const assign = vi.fn()
    vi.stubGlobal('location', { ...window.location, assign })
    vi.mocked(loginService.githubRedirect).mockResolvedValueOnce({ url: 'https://github.com/login/oauth/authorize?state=abc' })

    const { start, isRedirecting } = useGithubLogin()
    await start()

    expect(assign).toHaveBeenCalledWith('https://github.com/login/oauth/authorize?state=abc')
    expect(isRedirecting.value).toBe(true)
    vi.unstubAllGlobals()
  })

  it('shows a message when GitHub login is unavailable', async () => {
    vi.mocked(loginService.githubRedirect).mockRejectedValueOnce(new LoginError('boom', 500))

    const { start, githubError, isRedirecting } = useGithubLogin()
    await start()

    expect(githubError.value).toBe('Não deu para abrir o login com GitHub agora. Tente de novo ou entre com e-mail e senha.')
    expect(isRedirecting.value).toBe(false)
  })
})
