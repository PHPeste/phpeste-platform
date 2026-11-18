export class CfpSettingsError extends Error {
  statusCode: number | null
  fieldErrors: Record<string, string[]> | null

  constructor(message: string, statusCode: number | null = null, fieldErrors: Record<string, string[]> | null = null) {
    super(message)
    this.name = 'CfpSettingsError'
    this.statusCode = statusCode
    this.fieldErrors = fieldErrors
  }
}
