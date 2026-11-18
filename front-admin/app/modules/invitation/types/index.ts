export interface Invitation {
  editionId: string
  editionName: string
  email: string
  name: string | null
  roleLabel: string
  invitedBy: string | null
  accountExists: boolean
  expiresAt: Date
}

export interface AcceptInvitationInput {
  token: string
  name?: string
  password?: string
  passwordConfirmation?: string
}
