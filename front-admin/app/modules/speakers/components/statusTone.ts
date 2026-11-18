import type { BadgeStatus } from '@/application/shared/components/ui/Badge.vue'
import type { SpeakerStatus } from '../types'

export const STATUS_TONE: Record<SpeakerStatus, BadgeStatus> = {
  confirmed: 'ok', pending: 'warn', waitlisted: 'info', declined: 'neutral', withdrawn: 'neutral', no_response: 'danger', cancelled: 'neutral',
}
