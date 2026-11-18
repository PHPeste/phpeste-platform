import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '@/stores/authStore'
import proposalsService from '../services/proposalsService'
import { ProposalsError } from '../services/proposalsError'
import { draftKey, useProposalWizard } from './useProposalWizard'

vi.mock('../services/proposalsService', () => ({
  default: { cfp: vi.fn(), register: vi.fn(), login: vi.fn(), profile: vi.fn(), saveProfile: vi.fn(), list: vi.fn(), show: vi.fn(), create: vi.fn(), update: vi.fn(), submit: vi.fn(), withdraw: vi.fn(), confirm: vi.fn(), decline: vi.fn() },
}))

const proposalPayload = (overrides: Record<string, unknown> = {}) => ({
  id: 'p-1', number: 7, title: 'Octane em produção', track: { id: 't-1', name: 'Backend' }, summary: 'Resumo', audience: 'Quem usa Laravel',
  format: 'talk', format_label: 'Palestra de 30 min', level: 'intermediate', level_label: 'Intermediário', language: 'pt_BR',
  status: 'draft', status_label: 'Rascunho', decision: null, decision_label: null, missing_for_submission: [], speakers: [{ user_id: 'u-1', is_owner: true }], confirmation: null,
  ...overrides,
}) as never
const profilePayload = { data: { public: { display_name: 'Ana Martins', job_title: null, company: null, bio: null, city: 'João Pessoa', state: 'PB', links: { github: null, linkedin: null, website: null } }, private: { accessibility_needs: 'Rampa', dietary_restrictions: null }, email: 'ana@x.dev' } }

function fill(wizard: ReturnType<typeof useProposalWizard>) {
  Object.assign(wizard.talk, { title: 'Octane em produção', trackId: 't-1', format: 'talk', level: 'intermediate', summary: 'Resumo', audience: 'Quem usa Laravel' })
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  vi.useRealTimers()
  localStorage.clear()
  vi.mocked(proposalsService.profile).mockResolvedValue(profilePayload)
})

describe('useProposalWizard', () => {
  it('sem conta, guarda o rascunho no navegador e retoma depois de recarregar', async () => {
    const first = useProposalWizard('ed-1')
    await first.load()
    first.talk.title = 'Filas sem drama'
    await first.saveTalk()

    expect(first.saveState.value).toBe('local')
    expect(proposalsService.create).not.toHaveBeenCalled()
    expect(JSON.parse(localStorage.getItem(draftKey('ed-1'))!).talk.title).toBe('Filas sem drama')

    const reloaded = useProposalWizard('ed-1')
    await reloaded.load()
    expect(reloaded.talk.title).toBe('Filas sem drama')
    expect(reloaded.step.value).toBe(1)
  })

  it('depois de entrar, completa o perfil sem apagar o que já existe e salva o rascunho na plataforma', async () => {
    const wizard = useProposalWizard('ed-1')
    await wizard.load()
    wizard.talk.title = 'Filas sem drama'
    useAuthStore().setSession({ id: 'u-1', name: 'Ana', email: 'ana@x.dev', avatarUrl: null }, 'tok')
    vi.mocked(proposalsService.create).mockResolvedValueOnce({ data: proposalPayload({ title: 'Filas sem drama' }) })

    await wizard.afterLogin()

    expect(wizard.profile.displayName).toBe('Ana Martins')
    expect(wizard.profile.accessibilityNeeds).toBe('Rampa')
    expect(proposalsService.create).toHaveBeenCalledWith('ed-1', expect.objectContaining({ title: 'Filas sem drama', language: 'pt_BR' }))
    expect(JSON.parse(localStorage.getItem(draftKey('ed-1'))!).proposalId).toBe('p-1')
  })

  it('valida o perfil e a palestra antes de seguir, e cria uma vez e depois atualiza', async () => {
    useAuthStore().setSession({ id: 'u-1', name: 'Ana', email: 'ana@x.dev', avatarUrl: null }, 'tok')
    vi.mocked(proposalsService.saveProfile).mockResolvedValue(profilePayload)
    vi.mocked(proposalsService.create).mockResolvedValue({ data: proposalPayload() })
    vi.mocked(proposalsService.update).mockResolvedValue({ data: proposalPayload() })
    const wizard = useProposalWizard('ed-1')
    await wizard.load()

    wizard.profile.state = ''
    expect(await wizard.next()).toBe(false)
    expect(wizard.errors.value.state).toBe('Obrigatório.')
    wizard.profile.state = 'PB'
    expect(await wizard.next()).toBe(true)
    expect(proposalsService.saveProfile).toHaveBeenCalledWith(expect.objectContaining({ display_name: 'Ana Martins', state: 'PB', accessibility_needs: 'Rampa' }))

    wizard.talk.title = 'Só o título'
    expect(await wizard.next()).toBe(false)
    expect(wizard.errors.value).toMatchObject({ trackId: 'Obrigatório.', summary: 'Obrigatório.' })

    fill(wizard)
    expect(await wizard.next()).toBe(true)
    await wizard.saveTalk()
    expect(proposalsService.create).toHaveBeenCalledTimes(1)
    expect(proposalsService.update).toHaveBeenCalledWith('ed-1', 'p-1', expect.objectContaining({ track_id: 't-1' }))
    expect(wizard.step.value).toBe(3)
  })

  it('envia a proposta e limpa o rascunho do navegador', async () => {
    useAuthStore().setSession({ id: 'u-1', name: 'Ana', email: 'ana@x.dev', avatarUrl: null }, 'tok')
    vi.mocked(proposalsService.create).mockResolvedValue({ data: proposalPayload() })
    vi.mocked(proposalsService.submit).mockResolvedValue({ data: proposalPayload({ status: 'submitted', status_label: 'Enviada' }) })
    const wizard = useProposalWizard('ed-1')
    await wizard.load()
    fill(wizard)

    expect(await wizard.submit()).toBe(true)
    expect(proposalsService.submit).toHaveBeenCalledWith('ed-1', 'p-1')
    expect(wizard.submitted.value?.statusLabel).toBe('Enviada')
    expect(localStorage.getItem(draftKey('ed-1'))).toBeNull()
  })

  it('mostra a recusa do backend e volta ao perfil quando ele falta', async () => {
    useAuthStore().setSession({ id: 'u-1', name: 'Ana', email: 'ana@x.dev', avatarUrl: null }, 'tok')
    vi.mocked(proposalsService.create).mockResolvedValue({ data: proposalPayload() })
    vi.mocked(proposalsService.submit).mockRejectedValueOnce(new ProposalsError('x', 422, { speaker_profile: ['Complete seu perfil de palestrante antes de enviar a proposta.'] }))
    const wizard = useProposalWizard('ed-1')
    await wizard.load()
    fill(wizard)
    wizard.step.value = 3

    expect(await wizard.submit()).toBe(false)
    expect(wizard.message.value).toBe('Complete seu perfil de palestrante antes de enviar a proposta.')
    expect(wizard.step.value).toBe(1)
  })

  it('edita proposta enviada salvando direto, sem reenviar', async () => {
    useAuthStore().setSession({ id: 'u-1', name: 'Ana', email: 'ana@x.dev', avatarUrl: null }, 'tok')
    vi.mocked(proposalsService.show).mockResolvedValue({ data: proposalPayload({ status: 'submitted' }) })
    vi.mocked(proposalsService.update).mockResolvedValue({ data: proposalPayload({ status: 'submitted', title: 'Novo título' }) })
    const wizard = useProposalWizard('ed-1', 'p-1')
    await wizard.load()

    expect(wizard.step.value).toBe(2)
    expect(wizard.talk.title).toBe('Octane em produção')
    expect(wizard.isEditingSubmitted.value).toBe(true)
    wizard.talk.title = 'Novo título'
    expect(await wizard.submit()).toBe(true)
    expect(proposalsService.submit).not.toHaveBeenCalled()
    expect(proposalsService.update).toHaveBeenLastCalledWith('ed-1', 'p-1', expect.objectContaining({ title: 'Novo título' }))
  })
})
