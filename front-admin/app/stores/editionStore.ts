import { defineStore } from 'pinia'

export interface CurrentEdition {
  id: number
  slug: string
  name: string
}

interface EditionState {
  current: CurrentEdition | null
}

/**
 * Edição ativa do painel — lida por quase toda tela (seletor no topo,
 * chamadas de API escopadas por edição), por isso é store global e não
 * estado de um módulo só. Ainda não é alimentada por API nenhuma: o
 * módulo Community não existe no backend. Ver useEdition().
 */
export const useEditionStore = defineStore('edition', {
  state: (): EditionState => ({
    current: null,
  }),

  actions: {
    setCurrent(edition: CurrentEdition) {
      this.current = edition
    },

    clear() {
      this.current = null
    },
  },
})
