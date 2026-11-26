import { computed, ref } from 'vue'
import checkoutService, { type PlaceOrderBody } from '../services/checkoutService'
import locale from '../locales/pt-br'
import { failure } from './failure'
import { isCpf, isEmail } from './format'
import { saveCart, saveOrderToken, savedCart } from './storage'
import { translateQuote, translateTicket } from './translate'
import type { AttendeeForm, BuyerForm, Quote, SelectedLine, TicketOption } from '../types'

const FIELD_PREFIX = 'checkout-'

/** "items.0.attendees.1.name" → "checkout-items-0-attendees-1-name". */
export const fieldId = (path: string) => `${FIELD_PREFIX}${path.replace(/\./g, '-')}`

const newKey = () => (globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`)

export function useCheckout(editionId: string) {
  const tickets = ref<TicketOption[]>([])
  const soldOut = ref(false)
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  const step = ref<1 | 2>(1)
  const quantities = ref<Record<string, number>>({})
  const couponInput = ref('')
  const quote = ref<Quote | null>(null)
  const couponError = ref<string | null>(null)
  const couponBusy = ref(false)
  const buyer = ref<BuyerForm>({ name: '', email: '', document: '' })
  const attendees = ref<Record<string, AttendeeForm[]>>({})
  const errors = ref<Record<string, string>>({})
  const message = ref<string | null>(null)
  const busy = ref(false)
  let quoteRequest = 0
  let idempotency: { body: string, key: string } | null = null

  const lines = computed<SelectedLine[]>(() => tickets.value
    .filter((ticket) => ticket.onSale && (quantities.value[ticket.id] ?? 0) > 0)
    .map((ticket) => {
      const quantity = quantities.value[ticket.id] ?? 0
      return { type: ticket, quantity, subtotalCents: (ticket.priceCents ?? 0) * quantity }
    }))
  const count = computed(() => lines.value.reduce((sum, line) => sum + line.quantity, 0))
  const subtotalCents = computed(() => lines.value.reduce((sum, line) => sum + line.subtotalCents, 0))
  const discountCents = computed(() => quote.value?.discountCents ?? 0)
  const totalCents = computed(() => Math.max(0, subtotalCents.value - discountCents.value))
  const feePending = computed(() => totalCents.value > 0 && lines.value.some((line) => line.type.passFee))
  const requiresPayment = computed(() => totalCents.value > 0)

  const minErrors = computed(() => Object.fromEntries(lines.value
    .filter((line) => line.quantity < line.type.min)
    .map((line) => [line.type.id, locale.minError.replace('{min}', String(line.type.min)).replace('{nome}', line.type.name)])))

  /** Opções de quantidade: zero e o intervalo do tipo, sem passar do que resta. */
  const quantityOptions = (ticket: TicketOption) => {
    const max = Math.min(ticket.max, ticket.remaining ?? ticket.max, 50)
    return [0, ...Array.from({ length: Math.max(0, max - ticket.min + 1) }, (_, index) => ticket.min + index)]
  }

  async function load(restore = false) {
    state.value = 'loading'
    try {
      const catalog = (await checkoutService.catalog(editionId)).data
      tickets.value = catalog.ticket_types.map(translateTicket)
      soldOut.value = catalog.sold_out
      state.value = 'ready'
    } catch {
      state.value = 'error'
      return
    }
    if (!restore) return
    const cart = savedCart(editionId)
    if (!cart) return
    const next: Record<string, number> = {}
    for (const ticket of tickets.value) {
      const wanted = cart.quantities[ticket.id] ?? 0
      if (ticket.onSale && wanted > 0 && quantityOptions(ticket).includes(wanted)) next[ticket.id] = wanted
    }
    quantities.value = next
    if (cart.coupon && Object.keys(next).length) {
      couponInput.value = cart.coupon
      await applyCoupon()
    }
  }

  async function requote() {
    const code = quote.value?.code
    if (!code) return
    if (!lines.value.length) {
      quote.value = null
      return
    }
    const request = ++quoteRequest
    try {
      const response = await checkoutService.checkCoupon(editionId, code, lines.value.map((line) => ({ ticket_type_id: line.type.id, quantity: line.quantity })))
      if (request === quoteRequest) quote.value = translateQuote(response.data)
    } catch (error) {
      if (request !== quoteRequest) return
      quote.value = null
      couponError.value = failure(error).fieldErrors.coupon ?? locale.couponInvalid
    }
  }

  function setQuantity(ticketId: string, quantity: number) {
    quantities.value = { ...quantities.value, [ticketId]: quantity }
    message.value = null
    void requote()
  }

  async function applyCoupon() {
    const code = couponInput.value.trim().toUpperCase()
    couponError.value = null
    if (!code) {
      couponError.value = locale.requiredField
      return false
    }
    if (!lines.value.length) {
      couponError.value = locale.couponNeedsTickets
      return false
    }
    couponBusy.value = true
    const request = ++quoteRequest
    try {
      const response = await checkoutService.checkCoupon(editionId, code, lines.value.map((line) => ({ ticket_type_id: line.type.id, quantity: line.quantity })))
      if (request !== quoteRequest) return false
      quote.value = translateQuote(response.data)
      couponInput.value = quote.value.code
      return true
    } catch (error) {
      if (request !== quoteRequest) return false
      const result = failure(error)
      quote.value = null
      couponError.value = result.fieldErrors.code ?? result.fieldErrors.coupon ?? result.fieldErrors.items ?? result.message
      return false
    } finally {
      couponBusy.value = false
    }
  }

  function removeCoupon() {
    quoteRequest++
    quote.value = null
    couponInput.value = ''
    couponError.value = null
  }

  function syncAttendees() {
    const next: Record<string, AttendeeForm[]> = {}
    for (const line of lines.value) {
      const current = attendees.value[line.type.id] ?? []
      next[line.type.id] = Array.from({ length: line.quantity }, (_, index) => current[index] ?? { name: '', email: '', answers: {} })
    }
    attendees.value = next
  }

  function next(): boolean {
    message.value = null
    if (!lines.value.length) {
      message.value = locale.chooseFirst
      return false
    }
    if (Object.keys(minErrors.value).length) {
      message.value = Object.values(minErrors.value)[0]!
      return false
    }
    syncAttendees()
    errors.value = {}
    step.value = 2
    return true
  }

  function back() {
    errors.value = {}
    message.value = null
    step.value = 1
  }

  function copyBuyer(ticketId: string, index: number) {
    const list = attendees.value[ticketId]
    if (!list?.[index]) return
    list[index] = { ...list[index]!, name: buyer.value.name, email: buyer.value.email }
  }

  function validate(): Record<string, string> {
    const found: Record<string, string> = {}
    const person = (prefix: string, value: { name: string, email: string }) => {
      if (value.name.trim() === '') found[`${prefix}.name`] = locale.requiredField
      else if (value.name.trim().length < 2) found[`${prefix}.name`] = locale.nameShort
      if (value.email.trim() === '') found[`${prefix}.email`] = locale.requiredField
      else if (!isEmail(value.email)) found[`${prefix}.email`] = locale.emailInvalid
    }
    person('buyer', buyer.value)
    if (requiresPayment.value) {
      if (buyer.value.document.trim() === '') found['buyer.document'] = locale.requiredField
      else if (!isCpf(buyer.value.document)) found['buyer.document'] = locale.cpfInvalid
    } else if (buyer.value.document.trim() !== '' && !isCpf(buyer.value.document)) {
      found['buyer.document'] = locale.cpfInvalid
    }
    lines.value.forEach((line, itemIndex) => {
      ;(attendees.value[line.type.id] ?? []).forEach((attendee, position) => {
        const prefix = `items.${itemIndex}.attendees.${position}`
        person(prefix, attendee)
        for (const question of line.type.questions) {
          const answer = attendee.answers[question.id]
          if (question.required && (answer === undefined || (typeof answer === 'string' && answer.trim() === ''))) {
            found[`${prefix}.answers.${question.id}`] = locale.answerRequired.replace('{pergunta}', question.label)
          }
        }
      })
    })
    return found
  }

  const body = (): PlaceOrderBody => ({
    buyer: { name: buyer.value.name.trim(), email: buyer.value.email.trim(), document: buyer.value.document.trim() === '' ? null : buyer.value.document },
    coupon: quote.value?.code ?? null,
    items: lines.value.map((line) => ({
      ticket_type_id: line.type.id,
      attendees: (attendees.value[line.type.id] ?? []).map((attendee) => ({
        name: attendee.name.trim(),
        email: attendee.email.trim(),
        answers: Object.fromEntries(line.type.questions
          .map((question) => [question.id, attendee.answers[question.id]] as const)
          .filter((entry): entry is readonly [string, string | boolean] => entry[1] !== undefined && entry[1] !== '')),
      })),
    })),
  })

  /** Cria a reserva. A mesma tentativa repete a mesma Idempotency-Key, para não reservar duas vezes. */
  async function reserve(): Promise<string | null> {
    message.value = null
    errors.value = validate()
    if (Object.keys(errors.value).length) return null
    const payload = body()
    const serialized = JSON.stringify(payload)
    if (idempotency?.body !== serialized) idempotency = { body: serialized, key: newKey() }
    busy.value = true
    try {
      const order = (await checkoutService.placeOrder(editionId, payload, idempotency.key)).data
      if (order.access_token) saveOrderToken(order.id, order.access_token)
      saveCart(editionId, { quantities: Object.fromEntries(lines.value.map((line) => [line.type.id, line.quantity])), coupon: quote.value?.code ?? '' })
      return order.id
    } catch (error) {
      const result = failure(error)
      const fields = Object.fromEntries(Object.entries(result.fieldErrors).filter(([key]) => key.startsWith('buyer.') || /^items\.\d+\.attendees\./.test(key)))
      errors.value = fields
      const general = Object.entries(result.fieldErrors).find(([key]) => !(key in fields))
      message.value = general ? general[1] : (Object.keys(fields).length ? null : result.message)
      if (general && /^items|coupon/.test(general[0])) idempotency = null
      return null
    } finally {
      busy.value = false
    }
  }

  return {
    tickets, soldOut, state, step, quantities, couponInput, quote, couponError, couponBusy, buyer, attendees, errors, message, busy,
    lines, count, subtotalCents, discountCents, totalCents, feePending, requiresPayment, minErrors,
    quantityOptions, load, setQuantity, applyCoupon, removeCoupon, next, back, copyBuyer, reserve,
  }
}
