import { storeToRefs } from 'pinia'
import { useEditionStore } from '@/stores/editionStore'

/**
 * Edição atual do painel. Hoje só guarda em memória o que foi setado
 * explicitamente (ex.: seletor de edição) — passa a vir do backend
 * (`GET /api/v1/editions/current` ou equivalente) quando o módulo
 * Community existir. Ver docs/adr/, decisão de Identity/Community.
 */
export function useEdition() {
  const store = useEditionStore()
  const { current } = storeToRefs(store)

  return {
    current,
    setCurrent: store.setCurrent,
    clear: store.clear,
  }
}
