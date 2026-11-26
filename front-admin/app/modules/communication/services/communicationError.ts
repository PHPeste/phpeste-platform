export class CommunicationError extends Error {
  statusCode: number | null
  fieldErrors: Record<string, string[]> | null

  constructor(message: string, statusCode: number | null = null, fieldErrors: Record<string, string[]> | null = null) {
    super(message)
    this.name = 'CommunicationError'
    this.statusCode = statusCode
    this.fieldErrors = fieldErrors
  }
}
