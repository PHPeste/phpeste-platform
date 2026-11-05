import { computed } from 'vue'
import { canSee, findNavItem, NAV_ITEMS, type NavKey } from '@/application/shared/layout/navigation'
import { useEdition } from './useEdition'

/** O que o papel da pessoa, na edição atual, pode ver e abrir. */
export function usePermissions() {
  const { role } = useEdition()

  const visibleItems = computed(() => NAV_ITEMS.filter((item) => canSee(item, role.value)))

  function can(key: NavKey): boolean {
    const item = NAV_ITEMS.find((i) => i.key === key)
    return item ? canSee(item, role.value) : false
  }

  function canOpen(path: string): boolean {
    const item = findNavItem(path)
    return item === null || canSee(item, role.value)
  }

  return { role, visibleItems, can, canOpen }
}
