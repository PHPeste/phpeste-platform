import { AccountError } from '../services/accountError'
import locale from '../locales/pt-br'

const ALIASES: Record<string, string> = { holder_name: 'holderName', badge_name: 'badgeName', tshirt_size: 'tshirtSize' }

export interface Failure { ok: false, message: string, fieldErrors: Record<string, string> }

export function failure(error: unknown): Failure {
  if (error instanceof AccountError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([field, messages]) => [ALIASES[field] ?? field, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] || locale.genericError, fieldErrors }
  }
  if (error instanceof AccountError && error.statusCode === 429) return { ok: false, message: locale.tooMany, fieldErrors: {} }
  if (error instanceof AccountError && error.statusCode === null) return { ok: false, message: locale.offlineAction, fieldErrors: {} }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

export const isOffline = (error: unknown) => error instanceof AccountError && error.statusCode === null
