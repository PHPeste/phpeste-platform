import { computed, reactive, ref } from 'vue'
import editionSettingsService, { type AuditPayload } from '../services/editionSettingsService'
import locale from '../locales/pt-br'
import type { AuditEntry, AuditFilters } from '../types'

const show = (value: unknown) => {
  if (value === null || value === undefined || value === '') return '—'
  return locale.values[String(value)] ?? String(value)
}

export function describe(before: Record<string, unknown> | null, after: Record<string, unknown> | null): string {
  const keys = [...new Set([...Object.keys(before ?? {}), ...Object.keys(after ?? {})])]
  return keys
    .filter((key) => (before ?? {})[key] !== (after ?? {})[key])
    .map((key) => {
      const label = locale.fields[key] ?? key
      if (!before || !(key in before)) return `${label}: ${show(after?.[key])}`
      return `${label}: ${show(before[key])} → ${show(after?.[key])}`
    })
    .join(' · ')
}

function translate(payload: AuditPayload['data'][number]): AuditEntry {
  return {
    id: payload.id,
    action: payload.action,
    actionLabel: locale.actions[payload.action] ?? payload.action,
    actor: payload.actor ? { id: payload.actor.id, name: payload.actor.name ?? '' } : null,
    detail: describe(payload.before, payload.after),
    createdAt: payload.created_at,
  }
}

const startOfDay = (day: string) => new Date(`${day}T00:00:00-03:00`).toISOString()
const endOfDay = (day: string) => new Date(`${day}T23:59:59-03:00`).toISOString()

export function useAuditLog(editionId: string) {
  const entries = ref<AuditEntry[]>([])
  const people = ref<Array<{ id: string, name: string }>>([])
  const page = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const state = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')
  const filters = reactive<AuditFilters>({ action: '', actorId: '', from: '', to: '' })

  const hasFilters = computed(() => Object.values(filters).some(Boolean))

  async function load(target = 1): Promise<void> {
    state.value = 'loading'
    try {
      const payload = await editionSettingsService.audit(editionId, {
        page: target,
        action: filters.action || undefined,
        actor_id: filters.actorId || undefined,
        from: filters.from ? startOfDay(filters.from) : undefined,
        to: filters.to ? endOfDay(filters.to) : undefined,
      })
      entries.value = payload.data.map(translate)
      for (const entry of entries.value) {
        if (entry.actor && !people.value.some((person) => person.id === entry.actor!.id)) people.value = [...people.value, entry.actor]
      }
      page.value = payload.meta.current_page
      lastPage.value = payload.meta.last_page
      total.value = payload.meta.total
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  function clear() {
    Object.assign(filters, { action: '', actorId: '', from: '', to: '' })
    return load(1)
  }

  return { entries, people, page, lastPage, total, state, filters, hasFilters, load, clear }
}
