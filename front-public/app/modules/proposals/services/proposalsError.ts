export class ProposalsError extends Error {
  statusCode: number | null
  fieldErrors: Record<string, string[]> | null

  constructor(message: string, statusCode: number | null = null, fieldErrors: Record<string, string[]> | null = null) {
    super(message)
    this.name = 'ProposalsError'
    this.statusCode = statusCode
    this.fieldErrors = fieldErrors
  }
}
