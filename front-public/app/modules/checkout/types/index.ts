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

export interface Receiver {
  driver: string
  publicKey: string | null
  holderName: string
  holderType: 'individual' | 'company'
  methods: Array<'pix' | 'card'>
  maxInstallments: number
  sandbox: boolean
  ready: boolean
  /** Mercado Pago: cartão tokenizado no navegador. Os outros: cartão na página do gateway. */
  cardInBrowser: boolean
}

export type PaymentStatus = 'pending' | 'paid' | 'failed' | 'expired' | 'cancelled' | 'refunded' | 'disputed'

export interface Payment {
  id: string
  method: 'pix' | 'card' | 'boleto'
  status: PaymentStatus
  amountCents: number
  installments: number
  pix: { copyPaste: string, qrImage: string | null } | null
  checkoutUrl: string | null
  expiresAt: string | null
  failureReason: string | null
  orderStatus: OrderStatus
}

export interface CardDetails { token: string, brand: string | null, issuer: string | null, installments: number }

export interface IssuedTicket { id: string, code: string, typeName: string | null, holderName: string, qr: string | null }
