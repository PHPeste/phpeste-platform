export interface StatusOption {
  status: string
  label: string
  unlocks: string[]
  blocks: string[]
}

export interface EditionSettings {
  id: string
  name: string
  slug: string
  status: string
  statusLabel: string
  statusOptions: StatusOption[]
  startsOn: string
  endsOn: string
  city: string
  state: string
  timezone: string
  siteMode: 'platform' | 'external'
  externalSiteUrl: string
  siteUrl: string | null
  privacyContactEmail: string
  privacyPolicyUrl: string
  anonymizeAfterEvent: boolean
}

export type GeneralInput = Pick<EditionSettings, 'name' | 'slug' | 'startsOn' | 'endsOn' | 'city' | 'state' | 'timezone'>
export type SiteInput = Pick<EditionSettings, 'siteMode' | 'externalSiteUrl'>
export type PrivacyInput = Pick<EditionSettings, 'privacyContactEmail' | 'privacyPolicyUrl' | 'anonymizeAfterEvent'>

export interface Domain {
  id: string
  host: string
  isPlatform: boolean
  isPrimary: boolean
  verified: boolean
  txt: { name: string, value: string } | null
  cname: { name: string, value: string } | null
  lastCheckError: string | null
  url: string
}

export interface AuditEntry {
  id: string
  action: string
  actionLabel: string
  actor: { id: string, name: string } | null
  detail: string
  createdAt: string
}

export interface AuditFilters {
  action: string
  actorId: string
  from: string
  to: string
}

export type SaveResult = { ok: true } | { ok: false, message: string, fieldErrors: Record<string, string> }
