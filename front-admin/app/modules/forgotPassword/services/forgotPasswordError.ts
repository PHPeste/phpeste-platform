export class ForgotPasswordError extends Error {
  statusCode: number | null

  constructor(message: string, statusCode: number | null = null) {
    super(message)
    this.name = 'ForgotPasswordError'
    this.statusCode = statusCode
  }
}
