import { computed, ref } from 'vue'
import shellLocale from '@/modules/shell/locales/pt-br'
import type { EditionPayload } from '@/modules/shell/services/editionService'
import type { EditionRole } from '@/modules/shell/types'
import communitiesService, { type CommunityPayload } from '../services/communitiesService'
import locale from '../locales/pt-br'
import type { CommunitySummary, EditionSummary } from '../types'
import { formatEditionDates, initials, statusTone } from './format'

const ROLES: EditionRole[] = ['organizador', 'financeiro', 'avaliador', 'checkin', 'voluntario']

function translateEdition(payload: EditionPayload): EditionSummary {
  const role = ROLES.find((r) => r === payload.role) ?? null

  return {
    id: payload.id,
    name: payload.name,
    slug: payload.slug,
    status: payload.status,
    statusLabel: payload.status_label,
    statusTone: statusTone(payload.status),
    dates: formatEditionDates(payload.starts_on, payload.ends_on) ?? locale.datesToDefine,
    place: [payload.city, payload.state ? `(${payload.state})` : null].filter(Boolean).join(' '),
    isClosed: payload.status === 'closed',
    role,
  }
}

export function groupCommunities(editions: EditionPayload[], managed: CommunityPayload[]): CommunitySummary[] {
  const managedIds = new Set(managed.map((community) => community.id))
  const groups = new Map<string, CommunitySummary>()

  for (const community of managed) {
    groups.set(community.id, {
      id: community.id,
      name: community.name,
      slug: community.slug,
      initials: initials(community.slug, community.name),
      canManage: true,
      roleLabel: locale.organizerRole,
      editions: [],
    })
  }

  for (const payload of editions) {
    const community = payload.community ?? { id: payload.community_id, name: '', slug: '' }
    const edition = translateEdition(payload)

    if (!groups.has(community.id)) {
      groups.set(community.id, {
        id: community.id,
        name: community.name,
        slug: community.slug,
        initials: initials(community.slug, community.name),
        canManage: managedIds.has(community.id),
        roleLabel: edition.role ? shellLocale.roles[edition.role] : null,
        editions: [],
      })
    }

    groups.get(community.id)?.editions.push(edition)
  }

  return [...groups.values()]
}

export function useCommunities() {
  const communities = ref<CommunitySummary[]>([])
  const state = ref<'loading' | 'ready' | 'error'>('loading')

  async function load(): Promise<void> {
    state.value = 'loading'

    try {
      const [editions, managed] = await Promise.all([communitiesService.listEditions(), communitiesService.listManagedCommunities()])
      communities.value = groupCommunities(editions.data, managed.data)
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  const isEmpty = computed(() => state.value === 'ready' && communities.value.length === 0)

  return { communities, state, isEmpty, load }
}
