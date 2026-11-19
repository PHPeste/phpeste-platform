import { computed, ref } from 'vue'
import ticketsService, { type BatchPayload, type CouponPayload, type SettingsPayload, type TicketTypePayload } from '../services/ticketsService'
import { TicketsError } from '../services/ticketsError'
import locale from '../locales/pt-br'
import { localToIso } from './money'
import type { Batch, BatchInput, Coupon, CouponInput, MutationResult, SalesSettings, SettingsInput, TicketType, TicketTypeInput } from '../types'

const translateBatch = (payload: BatchPayload): Batch => ({
  id: payload.id,
  ticketTypeId: payload.ticket_type_id,
  name: payload.name,
  position: payload.position,
  priceCents: payload.price.amount,
  quantity: payload.quantity,
  sold: payload.allocated,
  remaining: payload.remaining,
  startsAt: payload.starts_at,
  endsAt: payload.ends_at,
  onSellOut: payload.on_sell_out,
  status: payload.status,
  statusLabel: payload.status_label,
})

const translateType = (payload: TicketTypePayload): TicketType => ({
  id: payload.id,
  name: payload.name,
  description: payload.description ?? '',
  visibility: payload.visibility,
  visibilityLabel: payload.visibility_label,
  accessCode: payload.access_code ?? '',
  minPerOrder: payload.min_per_order,
  maxPerOrder: payload.max_per_order,
  showRemaining: payload.show_remaining,
  passFeeToBuyer: payload.pass_fee_to_buyer,
  questions: payload.questions.map((q) => ({ label: q.label, type: q.type, required: q.required ?? false, options: q.options ?? [] })),
  position: payload.position,
  sold: payload.allocated,
  quantity: payload.quantity,
  currentBatchId: payload.current_batch_id,
  batches: payload.batches.map(translateBatch),
})

const translateSettings = (payload: SettingsPayload): SalesSettings => ({
  gatewayReady: payload.gateway_ready,
  capacity: payload.capacity,
  sold: payload.allocated,
  remaining: payload.remaining,
  reservationMinutes: payload.reservation_minutes,
  maxTicketsPerBuyer: payload.max_tickets_per_buyer,
  feeBasisPoints: payload.gateway_fee_basis_points,
})

const translateCoupon = (payload: CouponPayload): Coupon => ({
  id: payload.id,
  code: payload.code,
  description: payload.description ?? '',
  kind: payload.kind,
  kindLabel: payload.kind_label,
  value: payload.value,
  maxUses: payload.max_uses,
  used: payload.used,
  remaining: payload.remaining,
  startsAt: payload.starts_at,
  endsAt: payload.ends_at,
  ticketTypeIds: payload.ticket_type_ids,
  active: payload.active,
  status: payload.status,
  discountTotalCents: payload.discount_total.amount,
})

const FIELDS: Record<string, string> = {
  name: 'name', description: 'description', visibility: 'visibility', access_code: 'accessCode', min_per_order: 'minPerOrder', max_per_order: 'maxPerOrder',
  price_cents: 'price', quantity: 'quantity', starts_at: 'startsAt', ends_at: 'endsAt', on_sell_out: 'onSellOut', batch: 'batch', ticket_type: 'type',
  code: 'code', kind: 'kind', value: 'value', max_uses: 'maxUses', ticket_type_ids: 'ticketTypeIds', active: 'active', coupon: 'coupon',
  capacity: 'capacity', reservation_minutes: 'reservationMinutes', max_tickets_per_buyer: 'maxTicketsPerBuyer', gateway_fee_basis_points: 'feeBasisPoints',
}

function failure(error: unknown): MutationResult {
  if (error instanceof TicketsError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [FIELDS[key.split('.')[0] ?? key] ?? key, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] ?? locale.genericError, fieldErrors }
  }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

const orNull = (text: string) => (text.trim() === '' ? null : text.trim())

export function useTickets(editionId: string) {
  const types = ref<TicketType[]>([])
  const settings = ref<SalesSettings | null>(null)
  const coupons = ref<Coupon[]>([])
  const state = ref<'loading' | 'ready' | 'error'>('loading')

  async function loadTypes() {
    const payload = await ticketsService.types(editionId)
    types.value = payload.data.map(translateType)
    settings.value = translateSettings(payload.meta)
  }

  async function loadCoupons() {
    coupons.value = (await ticketsService.coupons(editionId)).data.map(translateCoupon)
  }

  async function load(): Promise<void> {
    state.value = 'loading'
    try {
      await Promise.all([loadTypes(), loadCoupons()])
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  async function mutate(request: () => Promise<unknown>, reload: () => Promise<void> = loadTypes): Promise<MutationResult> {
    try {
      await request()
      await reload()
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  /** Lote pago à venda (ou agendado) com a conta do gateway ainda não validada. */
  const salesPaused = computed(() => settings.value !== null && !settings.value.gatewayReady
    && types.value.some((type) => type.batches.some((batch) => batch.priceCents > 0 && batch.status !== 'ended' && batch.status !== 'sold_out')))

  const saveType = (type: TicketType | null, input: TicketTypeInput) => mutate(() => {
    const body = {
      name: input.name.trim(),
      description: orNull(input.description),
      visibility: input.visibility,
      access_code: input.visibility === 'code' ? orNull(input.accessCode) : null,
      min_per_order: Number(input.minPerOrder),
      max_per_order: Number(input.maxPerOrder),
      show_remaining: input.showRemaining,
      pass_fee_to_buyer: input.passFeeToBuyer,
      questions: input.questions.map((q) => ({ label: q.label.trim(), type: q.type, required: q.required, ...(q.type === 'select' ? { options: q.options.map((o) => o.trim()).filter(Boolean) } : {}) })),
    }
    return type ? ticketsService.updateType(editionId, type.id, body) : ticketsService.createType(editionId, body)
  })

  const removeType = (type: TicketType) => mutate(() => ticketsService.deleteType(editionId, type.id))

  async function moveType(type: TicketType, direction: -1 | 1): Promise<MutationResult> {
    const index = types.value.findIndex((item) => item.id === type.id)
    const other = types.value[index + direction]
    if (!other) return { ok: true }
    return mutate(async () => {
      await ticketsService.updateType(editionId, type.id, { position: other.position })
      await ticketsService.updateType(editionId, other.id, { position: type.position })
    })
  }

  const saveBatch = (type: TicketType, batch: Batch | null, input: BatchInput) => mutate(() => {
    const body = {
      name: input.name.trim(),
      price_cents: input.priceCents,
      quantity: Number(input.quantity),
      starts_at: localToIso(input.startsAt),
      ends_at: localToIso(input.endsAt),
      on_sell_out: input.onSellOut,
    }
    return batch ? ticketsService.updateBatch(editionId, type.id, batch.id, body) : ticketsService.createBatch(editionId, type.id, body)
  })

  const closeBatch = (batch: Batch) => mutate(() => ticketsService.closeBatch(editionId, batch.ticketTypeId, batch.id))
  const removeBatch = (batch: Batch) => mutate(() => ticketsService.deleteBatch(editionId, batch.ticketTypeId, batch.id))

  const saveCoupon = (coupon: Coupon | null, input: CouponInput) => mutate(() => {
    const body = {
      ...(coupon && coupon.used > 0 ? {} : { code: input.code.trim().toUpperCase() }),
      description: orNull(input.description),
      kind: input.kind,
      value: Number(input.value),
      max_uses: input.maxUses === null || String(input.maxUses) === '' ? null : Number(input.maxUses),
      starts_at: localToIso(input.startsAt),
      ends_at: localToIso(input.endsAt),
      ticket_type_ids: input.ticketTypeIds,
      active: input.active,
    }
    return coupon ? ticketsService.updateCoupon(editionId, coupon.id, body) : ticketsService.createCoupon(editionId, body as Required<typeof body>)
  }, loadCoupons)

  const removeCoupon = (coupon: Coupon) => mutate(() => ticketsService.deleteCoupon(editionId, coupon.id), loadCoupons)

  const saveSettings = (input: SettingsInput) => mutate(() => ticketsService.updateSettings(editionId, {
    capacity: input.capacity === null || String(input.capacity) === '' ? null : Number(input.capacity),
    reservation_minutes: Number(input.reservationMinutes),
    max_tickets_per_buyer: input.maxTicketsPerBuyer === null || String(input.maxTicketsPerBuyer) === '' ? null : Number(input.maxTicketsPerBuyer),
    gateway_fee_basis_points: Number(input.feeBasisPoints),
  }))

  return { types, settings, coupons, state, salesPaused, load, saveType, removeType, moveType, saveBatch, closeBatch, removeBatch, saveCoupon, removeCoupon, saveSettings }
}
