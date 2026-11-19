export type HolderType = 'individual' | 'company'
export type Environment = 'sandbox' | 'production'
export type MethodKey = 'pix' | 'card' | 'boleto'

export interface Driver {
  key: string
  label: string
  needsPublicKey: boolean
}

export interface Account {
  id: string
  driver: string
  driverLabel: string
  environment: Environment
  environmentLabel: string
  credentialsHint: string
  holderType: HolderType
  holderTypeLabel: string
  holderName: string
  holderDocument: string
  methods: string[]
  methodLabels: string[]
  maxInstallments: number
  pixExpirationMinutes: number
  active: boolean
  ready: boolean
  verifiedAt: string | null
  webhookVerifiedAt: string | null
  webhookRejections: number
  webhookRejectedAt: string | null
  retiredAt: string | null
  createdAt: string
  webhookUrl: string | null
  lastEventAt: string | null
}

export interface WebhookEvent {
  id: string
  type: string
  eventId: string
  chargeId: string | null
  status: 'received' | 'processing' | 'processed' | 'ignored' | 'failed'
  statusLabel: string
  result: string | null
  attempts: number
  canReprocess: boolean
  nextAttemptAt: string | null
  receivedAt: string
}

export interface AccountInput {
  driver: string
  environment: Environment
  apiKey: string
  webhookSecret: string
  publicKey: string
  holderType: HolderType
  holderName: string
  holderDocument: string
  methods: string[]
  maxInstallments: number
  pixExpirationMinutes: number
}

export interface SettingsInput {
  holderName: string
  methods: string[]
  maxInstallments: number
  pixExpirationMinutes: number
}

export type MutationResult =
  | { ok: true }
  | { ok: false, message: string, fieldErrors: Record<string, string>, needsConfirm?: boolean }
