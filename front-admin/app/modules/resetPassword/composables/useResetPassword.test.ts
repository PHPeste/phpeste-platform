import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '@/stores/authStore'
import resetPasswordService from '../services/resetPasswordService'
import { ResetPasswordError } from '../services/resetPasswordError'
import { useResetPassword } from './useResetPassword'

vi.mock('../services/resetPasswordService', () => ({ default: { reset: vi.fn() } }))

const input = { email: 'ada@example.com', token: 'tok', password: 'senhanova123', passwordConfirmation: 'senhanova123' }

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useResetPassword', () => {
  it('on success ends any local session, since the backend revoked them all', async () => {
    useAuthStore().setSession({ id: 'user-1', name: 'Ada', email: 'ada@example.com', avatarUrl: null }, 'old-token')
    vi.mocked(resetPasswordService.reset).mockResolvedValueOnce({ reset: true })

    const { submit, done } = useResetPassword()
    await submit(input)

    expect(done.value).toBe(true)
    expect(useAuthStore().isAuthenticated).toBe(false)
  })

  it('flags an invalid or expired link', async () => {
    vi.mocked(resetPasswordService.reset).mockRejectedValueOnce(new ResetPasswordError('x', 422, { token: ['expirou'] }))

    const { submit, invalidLink, done } = useResetPassword()
    await submit(input)

    expect(invalidLink.value).toBe(true)
    expect(done.value).toBe(false)
  })

  it('shows the API reason for a rejected password', async () => {
    vi.mocked(resetPasswordService.reset).mockRejectedValueOnce(new ResetPasswordError('x', 422, { password: ['Esta senha apareceu em um vazamento.'] }))

    const { submit, passwordError, invalidLink } = useResetPassword()
    await submit(input)

    expect(passwordError.value).toBe('Esta senha apareceu em um vazamento.')
    expect(invalidLink.value).toBe(false)
  })
})
