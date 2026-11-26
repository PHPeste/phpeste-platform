import { CheckoutError } from '../services/checkoutError'
import locale from '../locales/pt-br'

const ALIASES: Record<string, string> = { buyer_document: 'buyer.document' }

export interface Failure { message: string, fieldErrors: Record<string, string> }

export function failure(error: unknown): Failure {
  if (error instanceof CheckoutError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [ALIASES[key] ?? key, messages[0] ?? '']))
    return { message: Object.values(fieldErrors)[0] || locale.genericError, fieldErrors }
  }
  if (error instanceof CheckoutError && error.statusCode === null) return { message: locale.offline, fieldErrors: {} }
  return { message: locale.genericError, fieldErrors: {} }
}
