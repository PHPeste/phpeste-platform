export type Visibility = 'public' | 'code' | 'hidden'
export type SellOut = 'next' | 'close'
export type BatchStatus = 'scheduled' | 'on_sale' | 'sold_out' | 'ended'
export type QuestionType = 'text' | 'select' | 'boolean'
export type CouponKind = 'percent' | 'fixed'

export interface Question {
  label: string
  type: QuestionType
  required: boolean
  options: string[]
}

export interface Batch {
  id: string
  ticketTypeId: string
  name: string
  position: number
  priceCents: number
  quantity: number
  sold: number
  remaining: number
  startsAt: string | null
  endsAt: string | null
  onSellOut: SellOut
  status: BatchStatus
  statusLabel: string
}

export interface TicketType {
  id: string
  name: string
  description: string
  visibility: Visibility
  visibilityLabel: string
  accessCode: string
  minPerOrder: number
  maxPerOrder: number
  showRemaining: boolean
  passFeeToBuyer: boolean
  questions: Question[]
  position: number
  sold: number
  quantity: number
  currentBatchId: string | null
  batches: Batch[]
}

export interface SalesSettings {
  gatewayReady: boolean
  capacity: number | null
  sold: number
  remaining: number | null
  reservationMinutes: number
  maxTicketsPerBuyer: number | null
  feeBasisPoints: number
}

export interface Coupon {
  id: string
  code: string
  description: string
  kind: CouponKind
  kindLabel: string
  value: number
  maxUses: number | null
  used: number
  remaining: number | null
  startsAt: string | null
  endsAt: string | null
  ticketTypeIds: string[]
  active: boolean
  status: string
  discountTotalCents: number
}

export interface TicketTypeInput {
  name: string
  description: string
  visibility: Visibility
  accessCode: string
  minPerOrder: number
  maxPerOrder: number
  showRemaining: boolean
  passFeeToBuyer: boolean
  questions: Question[]
}

export interface BatchInput {
  name: string
  priceCents: number
  quantity: number
  startsAt: string
  endsAt: string
  onSellOut: SellOut
}

export interface CouponInput {
  code: string
  description: string
  kind: CouponKind
  value: number
  maxUses: number | null
  startsAt: string
  endsAt: string
  ticketTypeIds: string[]
  active: boolean
}

export interface SettingsInput {
  capacity: number | null
  reservationMinutes: number
  maxTicketsPerBuyer: number | null
  feeBasisPoints: number
}

export type MutationResult = { ok: true } | { ok: false, message: string, fieldErrors: Record<string, string> }
