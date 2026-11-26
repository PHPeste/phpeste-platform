import { ref } from 'vue'
import checkoutService from '../services/checkoutService'
import { orderToken } from './storage'
import { translateIssuedTicket } from './translate'
import type { IssuedTicket } from '../types'

const RETRIES = [1000, 2000, 4000, 8000]

/** Ingressos do pedido pago. A emissão acontece junto com o pagamento; se ainda não apareceram, tenta de novo algumas vezes. */
export function useIssuedTickets(editionId: string, orderId: string) {
  const token = orderToken(orderId)
  const tickets = ref<IssuedTicket[]>([])
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  let timer: ReturnType<typeof setTimeout> | null = null
  let tries = 0

  async function load() {
    if (!token) {
      state.value = 'error'
      return
    }
    try {
      tickets.value = (await checkoutService.tickets(editionId, orderId, token)).data.map(translateIssuedTicket)
    } catch {
      tickets.value = []
    }
    if (tickets.value.length || tries >= RETRIES.length) {
      state.value = tickets.value.length ? 'ready' : 'error'
      return
    }
    timer = setTimeout(load, RETRIES[tries++])
  }

  function stop() {
    if (timer) clearTimeout(timer)
  }

  return { tickets, state, load, stop }
}
