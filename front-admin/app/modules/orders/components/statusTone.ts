import type { BadgeStatus } from '@/application/shared/components/ui/Badge.vue'
import type { OrderStatus, RefundStatus } from '../types'

export const ORDER_TONE: Record<OrderStatus, BadgeStatus> = {
  created: 'neutral',
  awaiting_payment: 'warn',
  paid: 'ok',
  expired: 'neutral',
  cancelled: 'neutral',
  refunded: 'info',
}

export const REFUND_TONE: Record<RefundStatus, BadgeStatus> = {
  unconfirmed: 'warn',
  pending: 'info',
  completed: 'ok',
  failed: 'danger',
}
