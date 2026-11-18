import { ref } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import proposalsService from '../services/proposalsService'
import { ProposalsError } from '../services/proposalsError'
import { failure } from './failure'
import { translateProposal } from './translate'
import type { Proposal, Result } from '../types'

export function useMyProposals(editionId: string) {
  const auth = useAuthStore()
  const proposals = ref<Proposal[]>([])
  const state = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')

  async function load() {
    if (!auth.isAuthenticated) return
    state.value = 'loading'
    try {
      proposals.value = (await proposalsService.list(editionId)).data.map((payload) => translateProposal(payload, auth.user?.id ?? null))
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  return { proposals, state, load }
}

export function useMyProposal(editionId: string, id: string) {
  const auth = useAuthStore()
  const proposal = ref<Proposal | null>(null)
  const state = ref<'idle' | 'loading' | 'ready' | 'error' | 'not-found'>('idle')
  const busy = ref<string | null>(null)

  async function load() {
    if (!auth.isAuthenticated) return
    state.value = 'loading'
    try {
      proposal.value = translateProposal((await proposalsService.show(editionId, id)).data, auth.user?.id ?? null)
      state.value = 'ready'
    } catch (error) {
      state.value = error instanceof ProposalsError && error.statusCode === 404 ? 'not-found' : 'error'
    }
  }

  async function act(key: string, request: () => Promise<unknown>): Promise<Result> {
    busy.value = key
    try {
      await request()
      await load()
      return { ok: true }
    } catch (error) {
      return failure(error)
    } finally {
      busy.value = null
    }
  }

  return {
    proposal, state, busy, load,
    withdraw: () => act('withdraw', () => proposalsService.withdraw(editionId, id)),
    confirm: () => act('confirm', () => proposalsService.confirm(editionId, id)),
    decline: (reason: string) => act('decline', () => proposalsService.decline(editionId, id, reason.trim() || null)),
  }
}
