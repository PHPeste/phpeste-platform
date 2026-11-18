import { computed } from 'vue'
import type { ResolvedEdition } from '#shared/edition'

interface EditionState {
  edition: ResolvedEdition | null
  unavailable: boolean
}

export function useEditionState() {
  return useState<EditionState>('edition', () => ({ edition: null, unavailable: false }))
}

export function useCurrentEdition() {
  const state = useEditionState()
  const edition = computed(() => state.value.edition)
  const siteUrl = computed(() => {
    const current = edition.value
    if (!current) return null
    return current.siteMode === 'external' && current.externalSiteUrl ? current.externalSiteUrl : `https://${current.primaryHost}`
  })
  const logo = computed(() => edition.value?.theme?.logos.primary ?? null)

  return { edition, unavailable: computed(() => state.value.unavailable), siteUrl, logo }
}
