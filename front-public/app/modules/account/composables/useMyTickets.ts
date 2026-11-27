import { ref } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import accountService from '../services/accountService'
import { isOffline } from './failure'
import { saveTickets, savedTickets } from './storage'
import { translateTicket } from './translate'
import type { AccountTicket } from '../types'

export function useMyTickets(editionId: string) {
  const auth = useAuthStore()
  const tickets = ref<AccountTicket[]>([])
  const state = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')
  const savedAt = ref<string | null>(null)
  const emailVerified = ref(true)

  async function load() {
    const userId = auth.user?.id
    if (!auth.isAuthenticated || !userId) return
    if (state.value !== 'ready') state.value = 'loading'
    try {
      const response = await accountService.tickets(editionId)
      tickets.value = response.data.map(translateTicket)
      emailVerified.value = response.meta.email_verified
      savedAt.value = null
      saveTickets(editionId, userId, tickets.value)
      state.value = 'ready'
    } catch (error) {
      const saved = isOffline(error) ? savedTickets(editionId, userId) : null
      if (saved) {
        tickets.value = saved.tickets
        savedAt.value = saved.savedAt
        state.value = 'ready'
        return
      }
      state.value = 'error'
    }
  }

  return { tickets, state, savedAt, emailVerified, load }
}
