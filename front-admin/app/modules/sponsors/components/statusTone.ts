import type { BadgeStatus } from '@/application/shared/components/ui/Badge.vue'
import type { DeliverableStatus, Stage } from '../types'

export const STAGE_TONE: Record<Stage, BadgeStatus> = {
  prospecting: 'neutral',
  negotiating: 'info',
  closed: 'warn',
  paid: 'ok',
  lost: 'danger',
}

export const DELIVERABLE_TONE: Record<DeliverableStatus, BadgeStatus> = {
  pending: 'neutral',
  in_progress: 'info',
  done: 'ok',
}
