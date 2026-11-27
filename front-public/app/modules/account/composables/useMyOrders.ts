import { ref } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import accountService from '../services/accountService'
import { translateOrder } from './translate'
import type { AccountOrder } from '../types'

export function useMyOrders(editionId: string) {
  const auth = useAuthStore()
  const orders = ref<AccountOrder[]>([])
  const state = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')
  const emailVerified = ref(true)

  async function load() {
    if (!auth.isAuthenticated) return
    state.value = 'loading'
    try {
      const response = await accountService.orders(editionId)
      orders.value = response.data.map(translateOrder)
      emailVerified.value = response.meta.email_verified
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  return { orders, state, emailVerified, load }
}
