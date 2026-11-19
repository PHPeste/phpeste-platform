import { ref } from 'vue'
import paymentsService, { type AccountPayload, type WebhookEventPayload } from '../services/paymentsService'
import { PaymentsError } from '../services/paymentsError'
import locale from '../locales/pt-br'
import type { Account, AccountInput, Driver, MutationResult, SettingsInput, WebhookEvent } from '../types'

export const translateAccount = (payload: AccountPayload, drivers: Driver[] = []): Account => ({
  id: payload.id,
  driver: payload.driver,
  driverLabel: drivers.find((driver) => driver.key === payload.driver)?.label ?? payload.driver,
  environment: payload.environment,
  environmentLabel: payload.environment_label,
  credentialsHint: payload.credentials_hint,
  holderType: payload.holder.type,
  holderTypeLabel: payload.holder.type_label,
  holderName: payload.holder.name,
  holderDocument: payload.holder.document,
  methods: payload.methods.map((method) => method.key),
  methodLabels: payload.methods.map((method) => method.label),
  maxInstallments: payload.max_installments,
  pixExpirationMinutes: payload.pix_expiration_minutes,
  active: payload.active,
  ready: payload.ready,
  verifiedAt: payload.verified_at,
  webhookVerifiedAt: payload.webhook_verified_at,
  webhookRejections: payload.webhook_rejections.count,
  webhookRejectedAt: payload.webhook_rejections.last_at,
  retiredAt: payload.retired_at,
  createdAt: payload.created_at,
  webhookUrl: payload.webhook_url,
  lastEventAt: payload.last_event_at,
})

export const translateEvent = (payload: WebhookEventPayload): WebhookEvent => ({
  id: payload.id,
  type: payload.type,
  eventId: payload.event_id,
  chargeId: payload.gateway_charge_id,
  status: payload.status,
  statusLabel: payload.status_label,
  result: payload.result,
  attempts: payload.attempts,
  canReprocess: payload.can_reprocess,
  nextAttemptAt: payload.next_attempt_at,
  receivedAt: payload.received_at,
})

const FIELDS: Record<string, string> = {
  driver: 'driver', environment: 'environment', api_key: 'apiKey', webhook_secret: 'webhookSecret', public_key: 'publicKey',
  holder_type: 'holderType', holder_name: 'holderName', holder_document: 'holderDocument', methods: 'methods',
  max_installments: 'maxInstallments', pix_expiration_minutes: 'pixExpirationMinutes', confirm: 'confirm', account: 'account',
}

function failure(error: unknown): Extract<MutationResult, { ok: false }> {
  if (error instanceof PaymentsError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [FIELDS[key.split('.')[0] ?? key] ?? key, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] ?? locale.genericError, fieldErrors, needsConfirm: 'confirm' in fieldErrors }
  }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

export function usePayments(editionId: string) {
  const active = ref<Account | null>(null)
  const previous = ref<Account[]>([])
  const drivers = ref<Driver[]>([])
  const state = ref<'loading' | 'ready' | 'error'>('loading')

  const events = ref<WebhookEvent[]>([])
  const eventsPage = ref(1)
  const eventsPageCount = ref(1)
  const eventsStatus = ref('')
  const eventsState = ref<'loading' | 'ready' | 'error'>('loading')

  async function load() {
    state.value = 'loading'
    try {
      const response = await paymentsService.accounts(editionId)
      drivers.value = response.data.drivers.map((driver) => ({ key: driver.key, label: driver.label, needsPublicKey: driver.needs_public_key }))
      active.value = response.data.active ? translateAccount(response.data.active, drivers.value) : null
      previous.value = response.data.previous.map((account) => translateAccount(account, drivers.value))
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  async function loadEvents(page = eventsPage.value, status = eventsStatus.value) {
    eventsPage.value = page
    eventsStatus.value = status
    eventsState.value = 'loading'
    try {
      const response = await paymentsService.events(editionId, page, status)
      events.value = response.data.map(translateEvent)
      eventsPageCount.value = Math.max(1, response.meta.last_page)
      eventsState.value = 'ready'
    } catch {
      eventsState.value = 'error'
    }
  }

  async function create(input: AccountInput, confirm = false): Promise<MutationResult> {
    const needsPublicKey = drivers.value.find((driver) => driver.key === input.driver)?.needsPublicKey ?? false
    try {
      await paymentsService.create(editionId, {
        driver: input.driver,
        environment: input.environment,
        api_key: input.apiKey.trim(),
        webhook_secret: input.webhookSecret.trim(),
        ...(needsPublicKey ? { public_key: input.publicKey.trim() } : {}),
        holder_type: input.holderType,
        holder_name: input.holderName.trim(),
        holder_document: input.holderDocument.trim(),
        methods: input.methods,
        max_installments: input.methods.includes('card') ? input.maxInstallments : 1,
        pix_expiration_minutes: input.pixExpirationMinutes,
        confirm,
      })
      await load()
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  async function update(input: SettingsInput): Promise<MutationResult> {
    if (!active.value) return { ok: false, message: locale.genericError, fieldErrors: {} }
    try {
      const response = await paymentsService.update(editionId, active.value.id, {
        holder_name: input.holderName.trim(),
        methods: input.methods,
        max_installments: input.methods.includes('card') ? input.maxInstallments : 1,
        pix_expiration_minutes: input.pixExpirationMinutes,
      })
      active.value = translateAccount(response.data, drivers.value)
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  async function verify(): Promise<MutationResult> {
    if (!active.value) return { ok: false, message: locale.genericError, fieldErrors: {} }
    try {
      const response = await paymentsService.verify(editionId, active.value.id)
      active.value = translateAccount(response.data, drivers.value)
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  async function reprocess(event: WebhookEvent): Promise<WebhookEvent | null> {
    try {
      const updated = translateEvent((await paymentsService.reprocess(editionId, event.id)).data)
      events.value = events.value.map((item) => (item.id === updated.id ? updated : item))
      return updated
    } catch {
      return null
    }
  }

  return { active, previous, drivers, state, events, eventsPage, eventsPageCount, eventsStatus, eventsState, load, loadEvents, create, update, verify, reprocess }
}
