import { useAuthStore } from '@/stores/authStore'
import authService from '../services/authService'
import { useEdition } from './useEdition'

/**
 * Reused by every piece of navigation chrome (sidebar, navbar, mobile top
 * bar) — logout is an account action, not a route destination, so it
 * doesn't belong to any single module.
 */
export function useLogout() {
  const authStore = useAuthStore()

  async function logout() {
    try {
      await authService.logout()
    } catch {
      // Even if the call fails (network down, token already expired), the
      // local session still ends — staying "logged in" in the browser
      // without a valid token helps no one.
    }

    authStore.clearSession()
    useEdition().reset()
    await navigateTo('/login')
  }

  return { logout }
}
