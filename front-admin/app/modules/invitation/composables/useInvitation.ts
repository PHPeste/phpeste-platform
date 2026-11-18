import { ref } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import invitationService, { type InvitationPayload } from '../services/invitationService'
import { InvitationError } from '../services/invitationError'
import locale from '../locales/pt-br'
import type { AcceptInvitationInput, Invitation } from '../types'

type LoadState = 'loading' | 'ready' | 'not_found' | 'gone' | 'error'

const MONTHS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']

export function formatInvitationDate(date: Date): string {
  return `${date.getDate()} ${MONTHS[date.getMonth()]} ${date.getFullYear()}`
}

function translate(payload: InvitationPayload['data']): Invitation {
  return {
    editionId: payload.edition.id,
    editionName: payload.edition.name,
    email: payload.email,
    name: payload.name,
    roleLabel: payload.role_label,
    invitedBy: payload.invited_by,
    accountExists: payload.account_exists,
    expiresAt: new Date(payload.expires_at),
  }
}

export function useInvitation() {
  const authStore = useAuthStore()

  const state = ref<LoadState>('loading')
  const invitation = ref<Invitation | null>(null)
  const goneMessage = ref<string | null>(null)
  const isSubmitting = ref(false)
  const errorMessage = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]> | null>(null)

  async function load(token: string): Promise<void> {
    state.value = 'loading'

    try {
      const { data } = await invitationService.show(token)
      invitation.value = translate(data)
      state.value = 'ready'
    } catch (error) {
      if (error instanceof InvitationError && error.statusCode === 404) {
        state.value = 'not_found'
      } else if (error instanceof InvitationError && error.statusCode === 410) {
        goneMessage.value = error.message
        state.value = 'gone'
      } else {
        state.value = 'error'
      }
    }
  }

  async function accept(input: AcceptInvitationInput): Promise<boolean> {
    isSubmitting.value = true
    errorMessage.value = null
    fieldErrors.value = null

    try {
      const payload = await invitationService.accept(input)

      if (payload.token) {
        authStore.setSession({ id: payload.user.id, name: payload.user.name, email: payload.user.email, avatarUrl: null }, payload.token)
      }

      return true
    } catch (error) {
      if (!(error instanceof InvitationError)) {
        errorMessage.value = locale.genericError
      } else if (error.statusCode === 410) {
        goneMessage.value = error.message
        state.value = 'gone'
      } else if (error.statusCode === 422 && error.fieldErrors) {
        fieldErrors.value = error.fieldErrors
        errorMessage.value = Object.values(error.fieldErrors)[0]?.[0] ?? locale.genericError
      } else if (error.statusCode === 429) {
        errorMessage.value = locale.tooManyAttempts
      } else if (error.statusCode === 403) {
        errorMessage.value = error.message
      } else {
        errorMessage.value = locale.genericError
      }

      return false
    } finally {
      isSubmitting.value = false
    }
  }

  return { state, invitation, goneMessage, isSubmitting, errorMessage, fieldErrors, load, accept }
}
