export interface EmailTemplate {
  id: string
  label: string
  trigger: string
  transactional: boolean
  variables: string[]
  actionLabel: string
  subject: string
  body: string
  customized: boolean
  lastSentAt: string | null
}

export interface Content { subject: string, body: string }

export type ContentField = keyof Content

export interface Preview { subject: string, html: string }

export type Failure = { ok: false, message: string, fieldErrors: Partial<Record<ContentField, string>> }

export type MutationResult = { ok: true } | Failure

export type TestResult = { ok: true, email: string } | Failure
