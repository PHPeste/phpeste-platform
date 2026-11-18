import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { CfpReviewError } from './cfpReviewError'

export interface ProposalPayload {
  id: string
  number: number
  title: string
  track: { id: string, name: string } | null
  summary: string | null
  audience: string | null
  format: string | null
  format_label: string | null
  duration_minutes: number | null
  level: string | null
  level_label: string | null
  language: string
  nordeste: boolean
  speakers_hidden: boolean
  speakers?: Array<{ user_id: string, name: string | null, email: string | null, state: string | null, is_owner: boolean }>
}

export interface QueuePayload {
  data: Array<ProposalPayload & { my_review_status: 'pending' | 'draft' | 'submitted' }>
}

export interface EvaluationPayload {
  summary: { proposals: number, reviewed: number, without_reviewer: number, average_score: number | null, nordeste_percent: number | null, nordeste_quota_percent: number }
  criteria: Array<{ id: string, name: string, weight: number }>
  data: Array<ProposalPayload & { final_score: number | null, review_status: 'pending' | 'reviewed' | 'tie', reviews_assigned: number, reviews_submitted: number, criterion_averages: Record<string, number> }>
}

export interface ReviewPayload {
  status: 'draft' | 'submitted'
  scores: Record<string, number>
  comment: string | null
  weighted_score: number | null
  submitted_at: string | null
}

export interface ReviewShowPayload {
  data: {
    proposal: ProposalPayload
    criteria: Array<{ id: string, name: string, description: string | null, weight: number }>
    review: ReviewPayload | null
  }
}

const base = (editionId: string) => `/editions/${editionId}/cfp`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new CfpReviewError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new CfpReviewError('Request failed')
  }
}

export default {
  queue: (editionId: string) => call(() => httpClient.get<QueuePayload>(`${base(editionId)}/reviews/queue`)),
  evaluation: (editionId: string) => call(() => httpClient.get<EvaluationPayload>(`${base(editionId)}/evaluation`)),
  review: (editionId: string, proposalId: string) => call(() => httpClient.get<ReviewShowPayload>(`${base(editionId)}/proposals/${proposalId}/review`)),
  saveReview: (editionId: string, proposalId: string, body: { scores: Record<string, number>, comment: string | null, submit: boolean }) =>
    call(() => httpClient.put<{ data: ReviewPayload }>(`${base(editionId)}/proposals/${proposalId}/review`, { body })),
  otherReviews: (editionId: string, proposalId: string) =>
    call(() => httpClient.get<{ data: Array<ReviewPayload & { reviewer: string | null }> }>(`${base(editionId)}/proposals/${proposalId}/reviews`)),
  reviewers: (editionId: string) =>
    call(() => httpClient.get<{ data: Array<{ id: string, name: string | null, assigned: number, submitted: number }> }>(`${base(editionId)}/reviewers`)),
  assignments: (editionId: string, proposalId: string) =>
    call(() => httpClient.get<{ data: Array<{ reviewer_id: string, name: string | null }> }>(`${base(editionId)}/proposals/${proposalId}/assignments`)),
  assign: (editionId: string, proposalId: string, reviewerId: string) =>
    call(() => httpClient.post<{ data: { reviewer_id: string } }>(`${base(editionId)}/proposals/${proposalId}/assignments`, { body: { reviewer_id: reviewerId } })),
  unassign: (editionId: string, proposalId: string, reviewerId: string) =>
    call(() => httpClient.delete<null>(`${base(editionId)}/proposals/${proposalId}/assignments/${reviewerId}`)),
  autoAssign: (editionId: string, perProposal: number) =>
    call(() => httpClient.post<{ data: { created: number, unfilled: number[] } }>(`${base(editionId)}/assignments/auto`, { body: { reviewers_per_proposal: perProposal } })),
  exportCsv: (editionId: string) => call(() => httpClient.get<Blob>(`${base(editionId)}/evaluation/export`, { as: 'blob' })),
}
