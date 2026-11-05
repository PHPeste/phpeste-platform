import { defineStore } from 'pinia'
import type { EditionRole } from '@/modules/shell/types'

export interface CurrentEdition {
  id: number
  slug: string
  name: string
  // Detalhes vindos de GET /editions; opcionais porque `setCurrent` também aceita só o essencial.
  city?: string
  state?: string
  /** Últimos dois dígitos do ano, mostrados no selo ("27"). */
  yearBadge?: string
  siteUrl?: string | null
  /** Papel da pessoa logada nesta edição. */
  role?: EditionRole
}

type LoadStatus = 'idle' | 'loading' | 'ready' | 'error'

interface EditionState {
  current: CurrentEdition | null
  /** Edições em que a pessoa tem algum papel (seletor do topo). */
  editions: CurrentEdition[]
  status: LoadStatus
}

/**
 * Edição ativa do painel — lida por quase toda tela (seletor no topo,
 * chamadas de API escopadas por edição), por isso é store global e não
 * estado de um módulo só. Alimentada por useEdition().load().
 */
export const useEditionStore = defineStore('edition', {
  state: (): EditionState => ({
    current: null,
    editions: [],
    status: 'idle',
  }),

  actions: {
    setCurrent(edition: CurrentEdition) {
      this.current = edition
    },

    setEditions(editions: CurrentEdition[]) {
      this.editions = editions
    },

    setStatus(status: LoadStatus) {
      this.status = status
    },

    clear() {
      this.current = null
      this.editions = []
      this.status = 'idle'
    },
  },
})
