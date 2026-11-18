export interface NotificationOption {
  id: string
  label: string
  description: string
  enabled: boolean
}

export interface Account {
  id: string
  name: string
  email: string
  avatarUrl: string | null
  locale: string
  hasPassword: boolean
  providers: string[]
  notifications: NotificationOption[]
}

export interface Session {
  id: string
  name: string
  current: boolean
  lastUsedAt: string | null
  createdAt: string | null
}

export interface PasswordInput {
  currentPassword: string
  password: string
  passwordConfirmation: string
}

export type Result = { ok: true } | { ok: false, message: string, fieldErrors: Record<string, string> }
