import { ProposalsError } from '../services/proposalsError'
import locale from '../locales/pt-br'
import type { Result } from '../types'

const FIELDS: Record<string, string> = {
  display_name: 'displayName', job_title: 'jobTitle', 'links.github': 'github', 'links.linkedin': 'linkedin', 'links.website': 'website',
  accessibility_needs: 'accessibilityNeeds', dietary_restrictions: 'dietaryRestrictions', track_id: 'trackId', password_confirmation: 'passwordConfirmation',
}

export function failure(error: unknown): Extract<Result, { ok: false }> {
  if (error instanceof ProposalsError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [FIELDS[key] ?? key, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] ?? locale.genericError, fieldErrors }
  }
  if (error instanceof ProposalsError && error.statusCode === 401) return { ok: false, message: locale.invalidCredentials, fieldErrors: {} }
  if (error instanceof ProposalsError && error.statusCode === 403 && error.message) return { ok: false, message: error.message, fieldErrors: {} }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}
