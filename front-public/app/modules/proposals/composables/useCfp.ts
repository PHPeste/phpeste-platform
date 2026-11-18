import { ref } from 'vue'
import proposalsService from '../services/proposalsService'
import { translateCfp } from './translate'
import type { CfpInfo } from '../types'

export function useCfp(editionId: string) {
  const cfp = ref<CfpInfo | null>(null)
  const state = ref<'loading' | 'ready' | 'error'>('loading')

  async function load() {
    state.value = 'loading'
    try {
      cfp.value = translateCfp((await proposalsService.cfp(editionId)).data)
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  return { cfp, state, load }
}
