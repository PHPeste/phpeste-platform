import type { ResolvedEdition } from '#shared/edition'
import { useEditionState } from '@/application/shared/composables/useCurrentEdition'

export default defineNuxtPlugin(() => {
  const state = useEditionState()

  if (import.meta.server) {
    const event = useRequestEvent()
    state.value = { edition: (event?.context.edition ?? null) as ResolvedEdition | null, unavailable: !!event?.context.editionUnavailable }
    return
  }

  if (state.value.edition || state.value.unavailable) return

  const raw = document.getElementById('edition-data')?.textContent
  if (!raw) return
  try {
    const data = JSON.parse(raw) as { edition: ResolvedEdition | null, unavailable: boolean }
    state.value = { edition: data.edition, unavailable: data.unavailable }
  } catch {
    state.value = { edition: null, unavailable: false }
  }
})
