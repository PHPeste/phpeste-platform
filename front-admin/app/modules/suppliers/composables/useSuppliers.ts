import { computed, ref } from 'vue'
import suppliersService from '../services/suppliersService'
import { blank, failure, sumRows, translateDirectory, translateLink, translateTotals } from './translate'
import type { DirectoryEntry, Member, MutationResult, NewSupplierInput, SupplierLink, Totals } from '../types'

export function useSuppliers(editionId: string) {
  const suppliers = ref<SupplierLink[]>([])
  const totals = ref<Totals | null>(null)
  const members = ref<Member[]>([])
  const status = ref('')
  const service = ref('')
  const query = ref('')
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  const filtered = computed(() => status.value !== '' || service.value !== '' || query.value.trim() !== '')
  const rowSums = computed(() => sumRows(suppliers.value))
  const undecided = computed(() => (totals.value ? totals.value.byStatus.to_contact + totals.value.byStatus.quoting : 0))

  const directory = ref<DirectoryEntry[]>([])
  const directoryState = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')

  async function load() {
    state.value = 'loading'
    try {
      const response = await suppliersService.list(editionId, { status: status.value, service: service.value, q: query.value })
      suppliers.value = response.data.map(translateLink)
      totals.value = translateTotals(response.totals)
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  async function loadTeam() {
    try {
      const team = await suppliersService.team(editionId)
      members.value = team.data.members.map((member) => ({ id: member.user_id, name: member.name ?? member.email ?? '' }))
    } catch {
      members.value = []
    }
  }

  async function search(nextStatus: string, nextService: string, nextQuery: string) {
    status.value = nextStatus
    service.value = nextService
    query.value = nextQuery
    await load()
  }

  async function create(input: NewSupplierInput): Promise<MutationResult & { linkId?: string }> {
    try {
      const created = await suppliersService.create(editionId, {
        name: input.name.trim(),
        service: input.service,
        description: blank(input.description),
        document: blank(input.document),
        website: blank(input.website),
        city: blank(input.city),
        state: blank(input.state)?.toUpperCase() ?? null,
        owner_id: input.ownerId || null,
        ...(input.contactName.trim() ? { contacts: [{ name: input.contactName.trim(), role: null, email: blank(input.contactEmail), phone: blank(input.contactPhone) }] } : {}),
      })
      await load()
      return { ok: true, linkId: created.data.id }
    } catch (error) {
      return failure(error)
    }
  }

  /** Busca no cadastro da comunidade para trazer fornecedor de outra edição. */
  async function searchDirectory(text: string) {
    directoryState.value = 'loading'
    try {
      directory.value = (await suppliersService.directory(editionId, text)).data.map(translateDirectory)
      directoryState.value = 'ready'
    } catch {
      directoryState.value = 'error'
    }
  }

  async function reuse(supplierId: string, ownerId: string): Promise<MutationResult & { linkId?: string }> {
    try {
      const linked = await suppliersService.link(editionId, supplierId, ownerId || null)
      await load()
      return { ok: true, linkId: linked.data.id }
    } catch (error) {
      return failure(error)
    }
  }

  return { suppliers, totals, members, status, service, query, state, filtered, rowSums, undecided, directory, directoryState, load, loadTeam, search, create, searchDirectory, reuse }
}
