import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '@/stores/authStore'
import authService from '../services/authService'
import { useLogout } from './useLogout'

vi.mock('../services/authService', () => ({
  default: { logout: vi.fn() },
}))

const navigateToMock = vi.hoisted(() => vi.fn())
mockNuxtImport('navigateTo', () => navigateToMock)

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useLogout', () => {
  it('calls the logout endpoint, clears the session and redirects to /login', async () => {
    vi.mocked(authService.logout).mockResolvedValue(undefined)

    const authStore = useAuthStore()
    authStore.setSession({ id: 'user-1', name: 'Ada Lovelace', email: 'ada@example.com', avatarUrl: null }, 'abc123')

    localStorage.setItem('phpeste.account.tickets.ed-1.user-1', '{}')
    localStorage.setItem('phpeste.cfp.draft.ed-1', '{}')

    const { logout } = useLogout()
    await logout()

    expect(authService.logout).toHaveBeenCalledOnce()
    expect(localStorage.getItem('phpeste.account.tickets.ed-1.user-1')).toBeNull()
    expect(localStorage.getItem('phpeste.cfp.draft.ed-1')).toBe('{}')
    expect(authStore.token).toBeNull()
    expect(authStore.user).toBeNull()
    expect(navigateToMock).toHaveBeenCalledWith('/login')
  })

  it('still clears the session and redirects even if the API call fails', async () => {
    vi.mocked(authService.logout).mockRejectedValue(new Error('network down'))

    const authStore = useAuthStore()
    authStore.setSession({ id: 'user-1', name: 'Ada Lovelace', email: 'ada@example.com', avatarUrl: null }, 'abc123')

    const { logout } = useLogout()
    await logout()

    expect(authStore.token).toBeNull()
    expect(navigateToMock).toHaveBeenCalledWith('/login')
  })
})
