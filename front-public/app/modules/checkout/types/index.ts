export interface Question { id: string, label: string, type: 'text' | 'select' | 'boolean', required: boolean, options: string[] }

export interface TicketOption {
  id: string
  name: string
  description: string | null
  min: number
  max: number
  passFee: boolean
  questions: Question[]
  onSale: boolean
  statusLabel: string
  batchName: string | null
  priceCents: number | null
  remaining: number | null
  endsAt: string | null
  nextBatch: { name: string, priceCents: number, opensAt: string | null } | null
}

export interface Quote { code: string, subtotalCents: number, discountCents: number, totalCents: number }

export type Answer = string | boolean

export interface Person { name: string, email: string }

export interface AttendeeForm extends Person { answers: Record<string, Answer> }

export interface BuyerForm extends Person { document: string }

export interface SelectedLine { type: TicketOption, quantity: number, subtotalCents: number }

export interface OrderItem { id: string, ticketTypeName: string, batchName: string | null, unitPriceCents: number, discountCents: number, feeCents: number, totalCents: number, attendee: Person }

export type OrderStatus = 'created' | 'awaiting_payment' | 'paid' | 'expired' | 'cancelled' | 'refunded'

export interface Order {
  id: string
  number: number
  status: OrderStatus
  statusLabel: string
  buyer: Person
  subtotalCents: number
  discountCents: number
  feeCents: number
  totalCents: number
  requiresPayment: boolean
  couponCode: string | null
  expiresAt: string | null
  items: OrderItem[]
}
