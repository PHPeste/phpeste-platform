import { computed, ref } from 'vue'
import checkoutService from '../services/checkoutService'
import { CheckoutError } from '../services/checkoutError'
import { failure } from './failure'
import { orderToken } from './storage'
import { translateOrder } from './translate'
import type { Order } from '../types'

const HOLDING = ['created', 'awaiting_payment']

export function useOrder(editionId: string, orderId: string, clock: () => number = Date.now) {
  const order = ref<Order | null>(null)
  const state = ref<'loading' | 'ready' | 'not_found' | 'error'>('loading')
  const now = ref(clock())
  const busy = ref(false)
  const token = orderToken(orderId)
  let timer: ReturnType<typeof setInterval> | null = null

  const holding = computed(() => !!order.value && HOLDING.includes(order.value.status))
  const secondsLeft = computed(() => {
    const expiresAt = order.value?.expiresAt
    if (!holding.value || !expiresAt) return null
    return Math.max(0, Math.floor((new Date(expiresAt).getTime() - now.value) / 1000))
  })
  /** Reserva que acabou: expirou no backend ou o tempo zerou na tela. */
  const lapsed = computed<'expired' | 'cancelled' | null>(() => {
    if (order.value?.status === 'expired') return 'expired'
    if (order.value?.status === 'cancelled') return 'cancelled'
    return secondsLeft.value === 0 ? 'expired' : null
  })

  function tick() {
    now.value = clock()
  }

  function start() {
    stop()
    timer = setInterval(tick, 1000)
  }

  function stop() {
    if (timer) clearInterval(timer)
    timer = null
  }

  async function load() {
    if (!token) {
      state.value = 'not_found'
      return
    }
    if (!order.value) state.value = 'loading'
    try {
      order.value = translateOrder((await checkoutService.order(editionId, orderId, token)).data)
      tick()
      state.value = 'ready'
      if (holding.value) start()
    } catch (error) {
      state.value = error instanceof CheckoutError && error.statusCode === 404 ? 'not_found' : 'error'
    }
  }

  async function cancel(): Promise<string | null> {
    if (!token) return null
    busy.value = true
    try {
      order.value = translateOrder((await checkoutService.cancel(editionId, orderId, token)).data)
      stop()
      return null
    } catch (error) {
      return failure(error).message
    } finally {
      busy.value = false
    }
  }

  return { order, state, busy, holding, secondsLeft, lapsed, load, cancel, tick, stop }
}
