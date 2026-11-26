import { computed, ref } from 'vue'
import checkoutService, { type StartPaymentBody } from '../services/checkoutService'
import { failure } from './failure'
import { orderToken } from './storage'
import { translatePayment, translateReceiver } from './translate'
import locale from '../locales/pt-br'
import type { CardDetails, Payment, Receiver } from '../types'

/** Espera entre as consultas do status: começa rápido e vai espaçando até 30 s. */
export const POLL_DELAYS = [2000, 3000, 5000, 8000, 13000, 20000, 30000]

const newKey = () => (globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`)

export function usePayment(editionId: string, orderId: string) {
  const token = orderToken(orderId)
  const receiver = ref<Receiver | null>(null)
  const receiverState = ref<'loading' | 'ready' | 'error'>('loading')
  const payment = ref<Payment | null>(null)
  const busy = ref(false)
  const error = ref<string | null>(null)
  let polls = 0
  let timer: ReturnType<typeof setTimeout> | null = null
  let stopped = false
  let attempt: { body: string, key: string } | null = null

  const status = computed(() => payment.value?.status ?? null)
  const paid = computed(() => status.value === 'paid' || payment.value?.orderStatus === 'paid')

  async function loadReceiver() {
    receiverState.value = 'loading'
    try {
      const data = (await checkoutService.receiver(editionId)).data
      receiver.value = data ? translateReceiver(data) : null
      receiverState.value = 'ready'
    } catch {
      receiverState.value = 'error'
    }
  }

  function clear() {
    if (timer) clearTimeout(timer)
    timer = null
  }

  function schedule() {
    clear()
    if (stopped) return
    const delay = POLL_DELAYS[Math.min(polls, POLL_DELAYS.length - 1)]!
    polls++
    timer = setTimeout(refresh, delay)
  }

  function settle() {
    if (status.value === 'pending' && !paid.value) schedule()
    else clear()
  }

  /** Consulta o status da cobrança atual e decide se continua acompanhando. */
  async function refresh() {
    if (!token) return
    try {
      const data = (await checkoutService.payment(editionId, orderId, token)).data
      payment.value = data ? translatePayment(data) : null
    } catch {
      schedule()
      return
    }
    settle()
  }

  async function load() {
    stopped = false
    await Promise.all([loadReceiver(), refresh()])
  }

  /**
   * Inicia (ou retoma) a cobrança. Clique repetido enquanto a primeira está a caminho
   * não sai do navegador, e a mesma escolha repete a mesma Idempotency-Key.
   */
  async function start(method: 'pix' | 'card', installments = 1, card: CardDetails | null = null): Promise<boolean> {
    if (busy.value || !token) return false
    busy.value = true
    error.value = null
    const body: StartPaymentBody = { method, installments: card?.installments ?? installments }
    if (card) {
      body.card_token = card.token
      if (card.brand) body.card_brand = card.brand
      if (card.issuer) body.card_issuer = card.issuer
    }
    const serialized = JSON.stringify({ method: body.method, installments: body.installments, card: !!card })
    if (attempt?.body !== serialized || card) attempt = { body: serialized, key: newKey() }
    try {
      payment.value = translatePayment((await checkoutService.startPayment(editionId, orderId, token, body, attempt.key)).data)
      polls = 0
      if (payment.value.method === 'card' && payment.value.status === 'pending' && payment.value.checkoutUrl) {
        stopped = true
        clear()
        window.location.assign(payment.value.checkoutUrl)
        return true
      }
      settle()
      return true
    } catch (caught) {
      const result = failure(caught)
      error.value = result.fieldErrors.payment ?? result.fieldErrors.card_token ?? result.fieldErrors.method ?? result.fieldErrors.installments ?? result.message
      attempt = null
      return false
    } finally {
      busy.value = false
    }
  }

  /** Volta para a escolha do meio de pagamento depois de uma recusa. */
  function retry() {
    payment.value = null
    error.value = null
  }

  function stop() {
    stopped = true
    clear()
  }

  const failedMessage = computed(() => (status.value === 'failed' ? payment.value?.failureReason || locale.declinedGeneric : null))

  return { receiver, receiverState, payment, busy, error, status, paid, failedMessage, load, loadReceiver, refresh, start, retry, stop }
}
