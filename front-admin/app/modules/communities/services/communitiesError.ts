export class CommunitiesError extends Error {
  statusCode: number | null
  fieldErrors: Record<string, string[]> | null

  constructor(message: string, statusCode: number | null = null, fieldErrors: Record<string, string[]> | null = null) {
    super(message)
    this.name = 'CommunitiesError'
    this.statusCode = statusCode
    this.fieldErrors = fieldErrors
  }
}
