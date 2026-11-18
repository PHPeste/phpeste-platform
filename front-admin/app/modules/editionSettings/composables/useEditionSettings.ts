import { computed, reactive, ref } from 'vue'
import editionSettingsService, { type DomainPayload, type EditionPayload } from '../services/editionSettingsService'
import { EditionSettingsError } from '../services/editionSettingsError'
import locale from '../locales/pt-br'
import type { Domain, EditionSettings, GeneralInput, PrivacyInput, SaveResult, SiteInput } from '../types'

const FIELDS: Record<string, string> = {
  name: 'name', slug: 'slug', starts_on: 'startsOn', ends_on: 'endsOn', city: 'city', state: 'state', timezone: 'timezone',
  site_mode: 'siteMode', external_site_url: 'externalSiteUrl', privacy_contact_email: 'privacyContactEmail', privacy_policy_url: 'privacyPolicyUrl',
  anonymize_after_event: 'anonymizeAfterEvent', status: 'status', host: 'host',
}

export function translateEdition(payload: EditionPayload): EditionSettings {
  return {
    id: payload.id,
    name: payload.name,
    slug: payload.slug,
    status: payload.status,
    statusLabel: payload.status_label,
    statusOptions: payload.status_options,
    startsOn: payload.starts_on ?? '',
    endsOn: payload.ends_on ?? '',
    city: payload.city ?? '',
    state: payload.state ?? '',
    timezone: payload.timezone,
    siteMode: payload.site_mode,
    externalSiteUrl: payload.external_site_url ?? '',
    siteUrl: payload.site_url,
    privacyContactEmail: payload.privacy_contact_email ?? '',
    privacyPolicyUrl: payload.privacy_policy_url ?? '',
    anonymizeAfterEvent: payload.anonymize_after_event,
  }
}

const translateDomain = (payload: DomainPayload): Domain => ({
  id: payload.id,
  host: payload.host,
  isPlatform: payload.is_platform,
  isPrimary: payload.is_primary,
  verified: payload.verified,
  txt: payload.verification?.txt ?? null,
  cname: payload.verification?.cname ?? null,
  lastCheckError: payload.last_check_error,
  url: payload.url,
})

export function failure(error: unknown): Extract<SaveResult, { ok: false }> {
  if (error instanceof EditionSettingsError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [FIELDS[key] ?? key, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] ?? locale.genericError, fieldErrors }
  }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

const nullable = (value: string) => (value.trim() === '' ? null : value.trim())

export function useEditionSettings(editionId: string, onChanged: () => Promise<void> | void = () => {}) {
  const edition = ref<EditionSettings | null>(null)
  const domains = ref<Domain[]>([])
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  const saving = ref<'all' | 'status' | null>(null)

  const general = reactive<GeneralInput>({ name: '', slug: '', startsOn: '', endsOn: '', city: '', state: '', timezone: 'America/Fortaleza' })
  const site = reactive<SiteInput>({ siteMode: 'platform', externalSiteUrl: '' })
  const privacy = reactive<PrivacyInput>({ privacyContactEmail: '', privacyPolicyUrl: '', anonymizeAfterEvent: false })

  function accept(payload: EditionPayload) {
    edition.value = translateEdition(payload)
    Object.assign(general, { name: edition.value.name, slug: edition.value.slug, startsOn: edition.value.startsOn, endsOn: edition.value.endsOn, city: edition.value.city, state: edition.value.state, timezone: edition.value.timezone })
    Object.assign(site, { siteMode: edition.value.siteMode, externalSiteUrl: edition.value.externalSiteUrl })
    Object.assign(privacy, { privacyContactEmail: edition.value.privacyContactEmail, privacyPolicyUrl: edition.value.privacyPolicyUrl, anonymizeAfterEvent: edition.value.anonymizeAfterEvent })
  }

  async function loadDomains() {
    domains.value = (await editionSettingsService.domains(editionId)).data.map(translateDomain)
  }

  async function load(): Promise<void> {
    state.value = 'loading'
    try {
      const [{ data }] = await Promise.all([editionSettingsService.show(editionId), loadDomains()])
      accept(data)
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  const differs = <T extends object>(form: T) => !!edition.value && (Object.keys(form) as Array<keyof T>).some((key) => form[key] !== (edition.value as unknown as T)[key])
  const dirty = computed(() => ({ general: differs(general), site: differs(site), privacy: differs(privacy) }))

  const generalBody = () => ({
    name: general.name.trim(),
    ...(general.slug !== edition.value?.slug ? { slug: general.slug.trim() } : {}),
    starts_on: nullable(general.startsOn),
    ends_on: nullable(general.endsOn),
    city: nullable(general.city),
    state: nullable(general.state),
    timezone: general.timezone,
  })
  const siteBody = () => ({ site_mode: site.siteMode, external_site_url: nullable(site.externalSiteUrl) })
  const privacyBody = () => ({
    privacy_contact_email: nullable(privacy.privacyContactEmail),
    privacy_policy_url: nullable(privacy.privacyPolicyUrl),
    anonymize_after_event: privacy.anonymizeAfterEvent,
  })

  const anyDirty = computed(() => dirty.value.general || dirty.value.site || dirty.value.privacy)

  async function save(): Promise<SaveResult> {
    const body = {
      ...(dirty.value.general ? generalBody() : {}),
      ...(dirty.value.site ? siteBody() : {}),
      ...(dirty.value.privacy ? privacyBody() : {}),
    }
    if (!Object.keys(body).length) return { ok: true }

    saving.value = 'all'
    try {
      accept((await editionSettingsService.update(editionId, body)).data)
      await onChanged()
      return { ok: true }
    } catch (error) {
      return failure(error)
    } finally {
      saving.value = null
    }
  }

  async function changeStatus(status: string): Promise<SaveResult> {
    saving.value = 'status'
    try {
      accept((await editionSettingsService.changeStatus(editionId, status)).data)
      await onChanged()
      return { ok: true }
    } catch (error) {
      return failure(error)
    } finally {
      saving.value = null
    }
  }

  async function domainAction(request: () => Promise<unknown>): Promise<SaveResult> {
    try {
      await request()
      await loadDomains()
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  async function verifyDomain(domain: Domain): Promise<boolean | string> {
    try {
      const { data } = await editionSettingsService.verifyDomain(editionId, domain.id)
      await loadDomains()
      return data.verified
    } catch (error) {
      await loadDomains().catch(() => undefined)
      const result = failure(error)
      return result.fieldErrors.domain ?? result.message
    }
  }

  return {
    edition, domains, state, saving, general, site, privacy, dirty, anyDirty,
    load, save, changeStatus, verifyDomain,
    addDomain: (host: string) => domainAction(() => editionSettingsService.addDomain(editionId, host.trim().toLowerCase())),
    makePrimary: (domain: Domain) => domainAction(() => editionSettingsService.makePrimary(editionId, domain.id)),
    removeDomain: (domain: Domain) => domainAction(() => editionSettingsService.removeDomain(editionId, domain.id)),
  }
}
