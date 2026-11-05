import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import editionService, { type EditionPayload } from '@/modules/shell/services/editionService'
import type { EditionRole } from '@/modules/shell/types'
import { useEditionStore, type CurrentEdition } from '@/stores/editionStore'

const ROLES: EditionRole[] = ['organizador', 'financeiro', 'avaliador', 'checkin', 'voluntario']

function translate(payload: EditionPayload): CurrentEdition | null {
  const role = ROLES.find((r) => r === payload.role)
  if (!role) return null
  return {
    id: payload.id,
    slug: payload.slug,
    name: payload.name,
    city: payload.city,
    state: payload.state,
    yearBadge: String(payload.year).slice(-2),
    siteUrl: payload.site_url,
    role,
  }
}

/**
 * Edição atual do painel (store `edition`) + a lista de edições da pessoa e o
 * papel dela na edição ativa. O id escolhido fica num cookie (não é segredo).
 */
export function useEdition() {
  const store = useEditionStore()
  const { current, editions, status } = storeToRefs(store)
  const cookie = useCookie<number | null>('ph_edition', { default: () => null, sameSite: 'lax' })

  const role = computed<EditionRole | null>(() => current.value?.role ?? null)

  async function load(): Promise<void> {
    store.setStatus('loading')
    try {
      const response = await editionService.list()
      const list = response.data.map(translate).filter((e): e is CurrentEdition => e !== null)
      store.setEditions(list)
      const chosen = list.find((e) => e.id === cookie.value) ?? list[0]
      if (chosen) store.setCurrent(chosen)
      store.setStatus('ready')
    } catch {
      store.clear()
      store.setStatus('error')
    }
  }

  async function ensureLoaded(): Promise<void> {
    if (status.value === 'idle') await load()
  }

  /** Troca a edição, descarta os dados em cache e recarrega a rota atual. */
  async function select(id: number): Promise<void> {
    const next = editions.value.find((e) => e.id === id)
    if (!next || current.value?.id === id) return
    cookie.value = id
    store.setCurrent(next)
    clearNuxtData()
    await refreshNuxtData()
  }

  function clear() {
    store.clear()
    cookie.value = null
  }

  return {
    current,
    editions,
    status,
    role,
    setCurrent: store.setCurrent,
    clear,
    reset: clear,
    load,
    ensureLoaded,
    select,
  }
}
