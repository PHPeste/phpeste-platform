export interface CfpGeneral {
  opensOn: string
  closesOn: string
  resultsOn: string
  anonymousReview: boolean
  requireNordesteQuota: boolean
  nordesteQuotaPercent: number
  proposalsPerPerson: number
}

export interface CfpSettings extends CfpGeneral {
  status: string
  statusLabel: string
  daysUntilClose: number | null
}

export interface Track {
  id: string
  name: string
  slots: number
  position: number
  proposals: number
}

export interface Criterion {
  id: string
  name: string
  description: string
  weight: number
  position: number
}

export interface FormOption {
  id: string
  label: string
}

export interface ProposalFormOptions {
  formats: FormOption[]
  levels: FormOption[]
  languages: FormOption[]
  limits: { title: number, summary: number, audience: number }
}

export interface TrackInput {
  name: string
  slots: number
}

export interface CriterionInput {
  name: string
  description: string
  weight: number
}

export type MutationResult = { ok: true } | { ok: false, message: string, fieldErrors: Record<string, string> } | { ok: false, needsConfirmation: true }
