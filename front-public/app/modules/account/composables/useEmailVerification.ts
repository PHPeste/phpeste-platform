import { ref } from 'vue'
import accountService from '../services/accountService'

export function useEmailVerification() {
  const state = ref<'idle' | 'sending' | 'sent' | 'error'>('idle')

  async function resend() {
    if (state.value === 'sending') return
    state.value = 'sending'
    try {
      await accountService.resendVerification()
      state.value = 'sent'
    } catch {
      state.value = 'error'
    }
  }

  return { state, resend }
}
