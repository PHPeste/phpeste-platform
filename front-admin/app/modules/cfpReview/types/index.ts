export type ItemState = 'pending' | 'draft' | 'reviewed' | 'tie'
export type QueueFilter = 'all' | 'pending' | 'reviewed' | 'tie'

export interface ReviewItem {
  id: string
  number: number
  title: string
  track: string | null
  nordeste: boolean
  state: ItemState
  score: number | null
  reviewsSubmitted: number
  reviewsAssigned: number
  speakers: string[] | null
  criterionAverages: Record<string, number>
}

export interface ProposalDetail {
  id: string
  number: number
  title: string
  track: string | null
  format: string | null
  durationMinutes: number | null
  level: string | null
  language: string
  summary: string
  audience: string
  nordeste: boolean
  speakers: string[] | null
}

export interface Criterion {
  id: string
  name: string
  description: string | null
  weight: number
}

export interface MyReview {
  status: 'draft' | 'submitted'
  scores: Record<string, number>
  comment: string
  weightedScore: number | null
}

export interface OtherReview {
  reviewer: string | null
  scores: Record<string, number>
  comment: string
  weightedScore: number | null
}

export interface Reviewer {
  id: string
  name: string
  assigned: number
  submitted: number
}

export interface EvaluationSummary {
  proposals: number
  reviewed: number
  withoutReviewer: number
  averageScore: number | null
  nordestePercent: number | null
  nordesteQuotaPercent: number
}
