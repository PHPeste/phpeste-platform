import { useEdition } from '@/application/shared/composables/useEdition'

/** Torna a edição a ativa do painel e entra na visão geral dela. */
export function useOpenEdition() {
  const edition = useEdition()

  async function open(editionId: string, reload = false): Promise<void> {
    if (reload || !edition.editions.value.some((item) => item.id === editionId)) {
      await edition.load()
    }

    if (edition.current.value?.id !== editionId) {
      await edition.select(editionId)
    }

    await navigateTo('/')
  }

  return { open }
}
