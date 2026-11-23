export class AttendeesError extends Error {
  readonly statusCode: number | null
  readonly fieldErrors: Record<string, string[]> | null

  constructor(message: string, statusCode: number | null = null, fieldErrors: Record<string, string[]> | null = null) {
    super(message)
    this.name = 'AttendeesError'
    this.statusCode = statusCode
    this.fieldErrors = fieldErrors
  }
}
