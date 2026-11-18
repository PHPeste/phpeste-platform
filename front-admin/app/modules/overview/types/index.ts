export type Tone = 'ok' | 'warn' | 'danger' | 'info' | 'neutral'

export interface StageStep {
  status: string
  label: string
  note: string
  state: 'done' | 'current' | 'next' | 'upcoming'
}

export interface Indicator {
  label: string
  value: string
  hint: string
}

export interface PendingItem {
  key: string
  title: string
  tone: Tone
  tag: string
  actionLabel: string
  actionPath: string
}

export interface NordesteGoal {
  base: 'confirmed' | 'accepted'
  people: number
  percent: number | null
  quota: number
  requireQuota: boolean
  missingStates: string[]
}

export interface Milestone {
  key: string
  title: string
  description: string
  date: string
}

export interface WeekPoint {
  label: string
  value: number
}

export interface EditionOverview {
  subtitle: string
  daysUntilEvent: number | null
  steps: StageStep[]
  indicators: Indicator[]
  ticketsPerWeek: WeekPoint[] | null
  pending: PendingItem[]
  nordeste: NordesteGoal | null
  milestones: Milestone[]
}
