import { computed, ref } from 'vue'
import ordersService, { type HistoryPayload, type OrderPayload, type PaymentPayload, type RefundPayload } from '../services/ordersService'
import { OrdersError } from '../services/ordersError'
import locale from '../locales/pt-br'
import type { HistoryEntry, MutationResult, Order, OrderDetail, Payment, Refund, RefundInput, RefundResult } from '../types'

export const translateOrder = (payload: OrderPayload): Order => ({
  id: payload.id,
  number: payload.number,
  status: payload.status,
  statusLabel: payload.status_label,
  externalPlatform: payload.external?.platform ?? null,
  buyerName: payload.buyer.name,
  buyerEmail: payload.buyer.email,
  buyerDocument: payload.buyer.document,
  subtotalCents: payload.subtotal.amount,
  discountCents: payload.discount.amount,
  feeCents: payload.fee.amount,
  totalCents: payload.total.amount,
  refundedCents: payload.refunded.amount,
  gatewayFeeCents: payload.gateway_fee.amount,
  couponCode: payload.coupon_code,
  expiresAt: payload.expires_at,
  paidAt: payload.paid_at,
  createdAt: payload.created_at,
  items: payload.items.map((item) => ({
    id: item.id,
    ticketTypeName: item.ticket_type_name,
    batchName: item.batch_name,
    totalCents: item.total.amount,
    attendeeName: item.attendee.name,
    attendeeEmail: item.attendee.email,
    cancelled: item.cancelled_at !== null,
    externalCode: item.external_code,
  })),
  activeTickets: payload.items.filter((item) => item.cancelled_at === null).length,
})

const translatePayment = (payload: PaymentPayload | null): Payment | null => (payload === null ? null : {
  methodLabel: payload.method_label,
  status: payload.status,
  statusLabel: payload.status_label,
  installments: payload.installments,
  expiresAt: payload.expires_at,
  failureReason: payload.failure_reason,
})

const translateRefund = (payload: RefundPayload): Refund => ({
  id: payload.id,
  amountCents: payload.amount.amount,
  status: payload.status,
  statusLabel: payload.status_label,
  reasonLabel: payload.reason_label,
  note: payload.note,
  cancelledItems: payload.cancelled_item_ids.length,
  failureReason: payload.failure_reason,
  createdAt: payload.created_at,
})

const translateHistory = (payload: HistoryPayload): HistoryEntry => ({
  id: payload.id,
  kind: payload.kind,
  title: payload.title,
  detail: payload.detail,
  actorName: payload.actor ? (payload.actor.name ?? '') : null,
  at: payload.at,
})

const FIELDS: Record<string, string> = { justification: 'justification', amount_cents: 'amount', reason: 'reason', note: 'note', item_ids: 'items', cancel_tickets: 'items', order: 'order', status: 'order' }

function failure(error: unknown): { ok: false, message: string, fieldErrors: Record<string, string> } {
  if (error instanceof OrdersError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [FIELDS[key.split('.')[0] ?? key] ?? key, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] ?? locale.genericError, fieldErrors }
  }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

export function useOrders(editionId: string) {
  const orders = ref<Order[]>([])
  const page = ref(1)
  const pageCount = ref(1)
  const total = ref(0)
  const status = ref('')
  const query = ref('')
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  const filtered = computed(() => status.value !== '' || query.value.trim() !== '')

  const detail = ref<OrderDetail | null>(null)
  const detailState = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')

  async function load() {
    state.value = 'loading'
    try {
      const response = await ordersService.list(editionId, { page: page.value, status: status.value, q: query.value })
      orders.value = response.data.map(translateOrder)
      pageCount.value = Math.max(1, response.meta.last_page)
      total.value = response.meta.total
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  async function search(nextStatus: string, nextQuery: string) {
    status.value = nextStatus
    query.value = nextQuery
    page.value = 1
    await load()
  }

  async function goTo(next: number) {
    page.value = next
    await load()
  }

  async function open(orderId: string) {
    detailState.value = 'loading'
    try {
      const [shown, history, refunds] = await Promise.all([
        ordersService.show(editionId, orderId),
        ordersService.history(editionId, orderId),
        ordersService.refunds(editionId, orderId),
      ])
      detail.value = {
        order: translateOrder(shown.data),
        payment: translatePayment(shown.meta.payment),
        history: history.data.map(translateHistory),
        refunds: refunds.data.map(translateRefund),
      }
      orders.value = orders.value.map((order) => (order.id === orderId ? detail.value!.order : order))
      detailState.value = 'ready'
    } catch {
      detailState.value = 'error'
    }
  }

  function close() {
    detail.value = null
    detailState.value = 'idle'
  }

  async function mutate(request: () => Promise<unknown>): Promise<MutationResult> {
    const orderId = detail.value?.order.id
    if (!orderId) return { ok: false, message: locale.genericError, fieldErrors: {} }
    try {
      await request()
      await open(orderId)
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  const current = () => detail.value!.order.id

  const cancel = () => mutate(() => ordersService.cancel(editionId, current()))
  const markPaid = (justification: string) => mutate(() => ordersService.markPaid(editionId, current(), justification.trim()))
  const remind = () => mutate(() => ordersService.remind(editionId, current()))
  const settle = (refund: Refund, completed: boolean) => mutate(() => ordersService.settleRefund(editionId, current(), refund.id, completed))

  async function refund(input: RefundInput): Promise<RefundResult> {
    const orderId = detail.value?.order.id
    if (!orderId) return { ok: false, message: locale.genericError, fieldErrors: {} }
    try {
      const response = await ordersService.refund(editionId, orderId, {
        amount_cents: input.amountCents,
        reason: input.reason,
        note: input.note.trim() === '' ? null : input.note.trim(),
        cancel_tickets: input.cancelTickets,
        item_ids: input.cancelTickets ? input.itemIds : null,
      })
      await open(orderId)
      return { ok: true, refund: translateRefund(response.data) }
    } catch (error) {
      return failure(error)
    }
  }

  async function exportCsv(): Promise<Blob | null> {
    try {
      return await ordersService.exportCsv(editionId, { status: status.value, q: query.value })
    } catch {
      return null
    }
  }

  return { orders, page, pageCount, total, status, query, state, filtered, detail, detailState, load, search, goTo, open, close, cancel, markPaid, remind, refund, settle, exportCsv }
}

/** Quanto ainda pode voltar: o pago menos o que já foi reembolsado (ou reservado). */
export const refundableCents = (order: Order) => Math.max(0, order.totalCents - order.refundedCents)
