export type TshirtSize = 'PP' | 'P' | 'M' | 'G' | 'GG' | 'XGG' | 'BL-P' | 'BL-M' | 'BL-G' | 'BL-GG'

export const TSHIRT_SIZES: TshirtSize[] = ['PP', 'P', 'M', 'G', 'GG', 'XGG', 'BL-P', 'BL-M', 'BL-G', 'BL-GG']

export interface TicketTransfer { fromName: string, toName: string, toEmail: string, at: string | null }

export interface AccountTicket {
  id: string
  code: string
  active: boolean
  statusLabel: string
  orderNumber: number | null
  typeName: string | null
  batchName: string | null
  holderName: string
  holderEmail: string
  badgeName: string | null
  company: string | null
  tshirtSize: TshirtSize | null
  accessibility: string | null
  missing: string[]
  qr: string | null
  transfers: TicketTransfer[]
}

export interface BadgeForm {
  holderName: string
  badgeName: string
  company: string
  tshirtSize: TshirtSize | ''
  accessibility: string
}

export interface AccountOrderItem { id: string, typeName: string, batchName: string | null, attendeeName: string, totalCents: number, cancelled: boolean }

export interface AccountRefund { id: string, amountCents: number, status: string, statusLabel: string, reasonLabel: string, createdAt: string | null, completedAt: string | null }

export interface AccountOrder {
  id: string
  number: number
  status: string
  statusLabel: string
  pending: boolean
  subtotalCents: number
  discountCents: number
  feeCents: number
  totalCents: number
  refundedCents: number
  couponCode: string | null
  createdAt: string | null
  paidAt: string | null
  items: AccountOrderItem[]
  refunds: AccountRefund[]
}

export type Result = { ok: true } | { ok: false, message: string, fieldErrors: Record<string, string> }
