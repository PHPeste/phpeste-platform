import { beforeEach, describe, expect, it, vi } from 'vitest'
import communitiesService from '../services/communitiesService'
import { CommunitiesError } from '../services/communitiesError'
import { useNewEdition } from './useNewEdition'
import { useNewCommunity } from './useNewCommunity'

vi.mock('../services/communitiesService', () => ({
  default: { createCommunity: vi.fn(), createEdition: vi.fn(), cloneOptions: vi.fn(), clone: vi.fn(), cloneStatus: vi.fn() },
}))

beforeEach(() => {
  vi.clearAllMocks()
  vi.useRealTimers()
})

const input = { sourceEditionId: 'ed-27', name: 'PHPeste 2028', slug: '2028', parts: ['identity', 'team'] }

describe('useNewEdition', () => {
  it('cria em branco e devolve o id', async () => {
    vi.mocked(communitiesService.createEdition).mockResolvedValueOnce({ data: { id: 'ed-28' } } as never)

    expect(await useNewEdition().createBlank('c1', { name: 'PHPeste 2028', slug: '2028', startsOn: '', endsOn: '', city: '', state: '' })).toBe('ed-28')
  })

  it('carrega o que pode ser copiado e o que nunca é', async () => {
    vi.mocked(communitiesService.cloneOptions).mockResolvedValueOnce({ data: { parts: [{ id: 'identity', label: 'Identidade: tema e logos' }], never_copied: ['Propostas'] } })

    const { loadCloneOptions, cloneParts, neverCopied } = useNewEdition()
    await loadCloneOptions('ed-27')

    expect(cloneParts.value).toEqual([{ id: 'identity', label: 'Identidade: tema e logos' }])
    expect(neverCopied.value).toEqual(['Propostas'])
  })

  it('acompanha a cópia até terminar e devolve a edição nova', async () => {
    vi.useFakeTimers()
    vi.mocked(communitiesService.clone).mockResolvedValueOnce({ data: { id: 'clone-1', status: 'queued', target_edition_id: null, error: null } })
    vi.mocked(communitiesService.cloneStatus)
      .mockResolvedValueOnce({ data: { id: 'clone-1', status: 'queued', target_edition_id: null, error: null } })
      .mockResolvedValueOnce({ data: { id: 'clone-1', status: 'done', target_edition_id: 'ed-28', error: null } })

    const { createFromPrevious, isCloning } = useNewEdition()
    const pending = createFromPrevious(input)
    await vi.advanceTimersByTimeAsync(2000)

    expect(await pending).toBe('ed-28')
    expect(isCloning.value).toBe(false)
    expect(communitiesService.clone).toHaveBeenCalledWith(input)
  })

  it('explica a falha da cópia, que não cria nada', async () => {
    vi.mocked(communitiesService.clone).mockResolvedValueOnce({ data: { id: 'clone-1', status: 'failed', target_edition_id: null, error: 'Disco cheio' } })

    const { createFromPrevious, errorMessage } = useNewEdition()

    expect(await createFromPrevious(input)).toBeNull()
    expect(errorMessage.value).toBe('A cópia não terminou e nada foi criado. Motivo: Disco cheio')
  })

  it('devolve os erros de campo, como slug repetido', async () => {
    vi.mocked(communitiesService.clone).mockRejectedValueOnce(new CommunitiesError('x', 422, { slug: ['Já existe uma edição com este slug nesta comunidade.'] }))

    const { createFromPrevious, fieldErrors } = useNewEdition()
    await createFromPrevious(input)

    expect(fieldErrors.value?.slug).toEqual(['Já existe uma edição com este slug nesta comunidade.'])
  })
})

describe('useNewCommunity', () => {
  it('pede para confirmar o e-mail quando o backend recusa por isso', async () => {
    vi.mocked(communitiesService.createCommunity).mockRejectedValueOnce(new CommunitiesError('forbidden', 403))

    const { submit, errorMessage } = useNewCommunity()

    expect(await submit({ name: 'PHP-SE', slug: 'php-se' })).toBe(false)
    expect(errorMessage.value).toBe('Confirme seu e-mail pelo link que enviamos no cadastro antes de criar uma comunidade.')
  })
})
