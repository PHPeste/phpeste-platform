import { reactive, ref } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import accountService, { type TicketBody } from '../services/accountService'
import { AccountError } from '../services/accountError'
import { failure, isOffline } from './failure'
import { forgetTicket, saveTicket, savedTickets } from './storage'
import { translateTicket } from './translate'
import { isEmail } from './format'
import locale from '../locales/pt-br'
import type { AccountTicket, BadgeForm, Result } from '../types'

const LIMITS = { holderName: 120, badgeName: 60, company: 120, accessibility: 1000 } as const

const blank = (value: string) => value.trim() || null

export function badgeErrors(form: BadgeForm): Record<string, string> {
  const errors: Record<string, string> = {}
  if (form.holderName.trim().length < 2) errors.holderName = locale.holderNameRequired
  for (const [field, max] of Object.entries(LIMITS)) {
    if (form[field as keyof typeof LIMITS].trim().length > max) errors[field] = locale.tooLong.replace('{n}', String(max))
  }
  return errors
}

export function transferErrors(name: string, email: string, currentEmail: string): Record<string, string> {
  const errors: Record<string, string> = {}
  if (name.trim().length < 2) errors.name = locale.transferNameRequired
  if (!isEmail(email)) errors.email = locale.emailInvalid
  else if (email.trim().toLowerCase() === currentEmail.toLowerCase()) errors.email = locale.transferSameEmail
  return errors
}

export function useTicket(editionId: string, id: string) {
  const auth = useAuthStore()
  const ticket = ref<AccountTicket | null>(null)
  const state = ref<'idle' | 'loading' | 'ready' | 'error' | 'not-found' | 'transferred'>('idle')
  const offline = ref(false)
  const busy = ref<'save' | 'transfer' | 'resend' | null>(null)
  const transferredTo = ref<{ name: string, email: string } | null>(null)
  const form = reactive<BadgeForm>({ holderName: '', badgeName: '', company: '', tshirtSize: '', accessibility: '' })

  function fill(value: AccountTicket) {
    ticket.value = value
    Object.assign(form, { holderName: value.holderName, badgeName: value.badgeName ?? '', company: value.company ?? '', tshirtSize: value.tshirtSize ?? '', accessibility: value.accessibility ?? '' })
  }

  function remember(value: AccountTicket) {
    if (auth.user) saveTicket(editionId, auth.user.id, value)
  }

  async function load() {
    if (!auth.isAuthenticated) return
    if (state.value !== 'ready') state.value = 'loading'
    try {
      const value = translateTicket((await accountService.ticket(editionId, id)).data)
      fill(value)
      remember(value)
      offline.value = false
      state.value = 'ready'
    } catch (error) {
      const saved = isOffline(error) && auth.user ? savedTickets(editionId, auth.user.id)?.tickets.find((item) => item.id === id) : undefined
      if (saved) {
        fill(saved)
        offline.value = true
        state.value = 'ready'
        return
      }
      if (error instanceof AccountError && error.statusCode === 404) {
        if (auth.user) forgetTicket(editionId, auth.user.id, id)
        state.value = 'not-found'
        return
      }
      state.value = 'error'
    }
  }

  async function run(key: 'save' | 'transfer' | 'resend', request: () => Promise<void>): Promise<Result> {
    if (busy.value) return { ok: false, message: '', fieldErrors: {} }
    busy.value = key
    try {
      await request()
      return { ok: true }
    } catch (error) {
      return failure(error)
    } finally {
      busy.value = null
    }
  }

  function save(): Promise<Result> {
    const errors = badgeErrors(form)
    if (Object.keys(errors).length) return Promise.resolve({ ok: false, message: Object.values(errors)[0]!, fieldErrors: errors })
    const body: TicketBody = {
      holder_name: form.holderName.trim(),
      badge_name: blank(form.badgeName),
      company: blank(form.company),
      tshirt_size: form.tshirtSize || null,
      accessibility: blank(form.accessibility),
    }
    return run('save', async () => {
      const value = translateTicket((await accountService.update(editionId, id, body)).data)
      const updated = { ...value, transfers: ticket.value?.transfers ?? [] }
      fill(updated)
      remember(updated)
    })
  }

  function transfer(name: string, email: string): Promise<Result> {
    const errors = transferErrors(name, email, ticket.value?.holderEmail ?? '')
    if (Object.keys(errors).length) return Promise.resolve({ ok: false, message: Object.values(errors)[0]!, fieldErrors: errors })
    return run('transfer', async () => {
      await accountService.transfer(editionId, id, { name: name.trim(), email: email.trim() })
      if (auth.user) forgetTicket(editionId, auth.user.id, id)
      transferredTo.value = { name: name.trim(), email: email.trim().toLowerCase() }
      ticket.value = null
      state.value = 'transferred'
    })
  }

  const resend = () => run('resend', async () => { await accountService.resend(editionId, id) })

  return { ticket, state, offline, busy, form, transferredTo, load, save, transfer, resend }
}
