import type { ProposalPayload, PublicCfpPayload, SpeakerProfilePayload } from '../services/proposalsService'
import type { CfpInfo, ProfileForm, Proposal } from '../types'

const options = (items: Array<{ id: string, label?: string, name?: string }>) => items.map((item) => ({ value: item.id, label: item.label ?? item.name ?? '' }))

export function translateCfp(payload: PublicCfpPayload['data']): CfpInfo {
  return {
    status: payload.status,
    statusLabel: payload.status_label,
    opensAt: payload.opens_at,
    closesAt: payload.closes_at,
    anonymousReview: payload.anonymous_review,
    proposalsPerPerson: payload.proposals_per_person,
    tracks: options(payload.tracks),
    formats: options(payload.form.formats),
    levels: options(payload.form.levels),
    languages: options(payload.form.languages),
    limits: payload.form.limits,
  }
}

export function translateProfile(payload: SpeakerProfilePayload['data']): ProfileForm {
  const profile = payload.public
  return {
    displayName: profile?.display_name ?? '',
    jobTitle: profile?.job_title ?? '',
    company: profile?.company ?? '',
    city: profile?.city ?? '',
    state: profile?.state ?? '',
    bio: profile?.bio ?? '',
    github: profile?.links.github ?? '',
    linkedin: profile?.links.linkedin ?? '',
    website: profile?.links.website ?? '',
    accessibilityNeeds: payload.private.accessibility_needs ?? '',
    dietaryRestrictions: payload.private.dietary_restrictions ?? '',
  }
}

export function translateProposal(payload: ProposalPayload, userId: string | null): Proposal {
  return {
    id: payload.id,
    number: payload.number,
    title: payload.title,
    track: payload.track?.name ?? null,
    trackId: payload.track?.id ?? null,
    format: payload.format,
    formatLabel: payload.format_label,
    level: payload.level,
    levelLabel: payload.level_label,
    language: payload.language,
    summary: payload.summary ?? '',
    audience: payload.audience ?? '',
    status: payload.status,
    statusLabel: payload.status_label,
    decision: payload.decision,
    decisionLabel: payload.decision_label,
    missing: payload.missing_for_submission,
    isOwner: !payload.speakers || payload.speakers.some((speaker) => speaker.is_owner && speaker.user_id === userId),
    confirmation: payload.confirmation
      ? { status: payload.confirmation.status, statusLabel: payload.confirmation.status_label, deadlineAt: payload.confirmation.deadline_at, respondedAt: payload.confirmation.responded_at }
      : null,
  }
}

export const nullable = (value: string) => (value.trim() === '' ? null : value.trim())

export function longDate(iso: string | null): string {
  return iso ? new Date(iso).toLocaleDateString('pt-BR', { day: 'numeric', month: 'long', timeZone: 'America/Sao_Paulo' }) : ''
}
