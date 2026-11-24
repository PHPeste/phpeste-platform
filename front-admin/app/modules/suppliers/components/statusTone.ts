import type { BadgeStatus } from '@/application/shared/components/ui/Badge.vue'
import type { QuoteStatus, Status } from '../types'

export const STATUS_TONE: Record<Status, BadgeStatus> = {
  to_contact: 'neutral',
  quoting: 'info',
  hired: 'ok',
  declined: 'danger',
}

export const QUOTE_TONE: Record<QuoteStatus, BadgeStatus> = {
  received: 'neutral',
  accepted: 'ok',
  rejected: 'danger',
}
