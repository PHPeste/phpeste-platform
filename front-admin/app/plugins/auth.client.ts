import { useAuthStore } from '@/stores/authStore'
import { httpClient } from '@/infrastructure/http/HttpClient'

export default defineNuxtPlugin(() => {
  const authStore = useAuthStore()
  authStore.hydrate()

  httpClient.setUnauthorizedHandler(() => {
    authStore.clearSession()
    navigateTo('/login')
  })
})
