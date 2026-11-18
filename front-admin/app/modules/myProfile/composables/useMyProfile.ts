import { reactive, ref } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import myProfileService, { type SessionPayload, type UserPayload } from '../services/myProfileService'
import { MyProfileError } from '../services/myProfileError'
import locale from '../locales/pt-br'
import type { Account, PasswordInput, Result, Session } from '../types'

const FIELDS: Record<string, string> = { current_password: 'currentPassword', password: 'password', password_confirmation: 'passwordConfirmation', name: 'name', locale: 'locale', photo: 'photo' }

function translate(payload: UserPayload): Account {
  return {
    id: payload.id, name: payload.name, email: payload.email, avatarUrl: payload.avatar_url, locale: payload.locale,
    hasPassword: payload.has_password, providers: payload.providers, notifications: payload.notifications,
  }
}

const translateSession = (payload: SessionPayload): Session => ({ id: payload.id, name: payload.name, current: payload.current, lastUsedAt: payload.last_used_at, createdAt: payload.created_at })

function failure(error: unknown): Extract<Result, { ok: false }> {
  if (error instanceof MyProfileError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [FIELDS[key] ?? key, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] ?? locale.genericError, fieldErrors }
  }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

export function useMyProfile() {
  const auth = useAuthStore()
  const account = ref<Account | null>(null)
  const sessions = ref<Session[]>([])
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  const form = reactive({ name: '', locale: 'pt_BR' })
  const busy = ref<string | null>(null)

  function accept(payload: UserPayload) {
    account.value = translate(payload)
    form.name = account.value.name
    form.locale = account.value.locale
    auth.updateUser({ id: payload.id, name: payload.name, email: payload.email, avatarUrl: payload.avatar_url })
  }

  async function loadSessions() {
    sessions.value = (await myProfileService.sessions()).data.map(translateSession)
  }

  async function load(): Promise<void> {
    state.value = 'loading'
    try {
      const [{ user }] = await Promise.all([myProfileService.me(), loadSessions()])
      accept(user)
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  async function run(key: string, request: () => Promise<unknown>): Promise<Result> {
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

  const isDirty = () => !!account.value && (form.name.trim() !== account.value.name || form.locale !== account.value.locale)

  const saveAccount = () => run('account', async () => accept((await myProfileService.update({ name: form.name.trim(), locale: form.locale })).data))
  const uploadPhoto = (file: File) => run('photo', async () => accept((await myProfileService.uploadPhoto(file)).data))
  const removePhoto = () => run('photo', async () => accept((await myProfileService.removePhoto()).data))

  async function setNotification(id: string, enabled: boolean): Promise<Result> {
    const result = await run(`notification:${id}`, async () => accept((await myProfileService.update({ notifications: { [id]: enabled } })).data))
    return result
  }

  async function changePassword(input: PasswordInput): Promise<Result> {
    if (input.password !== input.passwordConfirmation) {
      return { ok: false, message: locale.passwordMismatch, fieldErrors: { passwordConfirmation: locale.passwordMismatch } }
    }
    const result = await run('password', () => myProfileService.changePassword({
      current_password: account.value?.hasPassword ? input.currentPassword : null,
      password: input.password,
      password_confirmation: input.passwordConfirmation,
    }))
    if (result.ok) {
      if (account.value) account.value = { ...account.value, hasPassword: true }
      await loadSessions()
    }
    return result
  }

  async function endSession(session: Session): Promise<Result> {
    const result = await run(`session:${session.id}`, () => myProfileService.endSession(session.id))
    if (result.ok) sessions.value = sessions.value.filter((item) => item.id !== session.id)
    return result
  }

  return { account, sessions, state, form, busy, isDirty, load, saveAccount, uploadPhoto, removePhoto, setNotification, changePassword, endSession }
}
