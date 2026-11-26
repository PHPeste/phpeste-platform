import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import communicationService from '../services/communicationService'
import { CommunicationError } from '../services/communicationError'
import { useEmailTemplates } from './useEmailTemplates'
import { previewPayload, templatePayload } from './fixtures'

vi.mock('../services/communicationService', () => ({
  default: { list: vi.fn(), save: vi.fn(), restore: vi.fn(), preview: vi.fn(), sendTest: vi.fn() },
}))

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(communicationService.list).mockResolvedValue({
    data: [
      templatePayload('order_confirmed', 'Confirmação de compra', { last_sent_at: '2027-03-10T15:42:00Z' }),
      templatePayload('event_reminder', 'Antes do evento', { transactional: false, variables: ['nome', 'edicao', 'data'], customized: true, subject: 'Falta pouco: {edicao}' }),
    ],
  })
  vi.mocked(communicationService.preview).mockResolvedValue(previewPayload())
})

afterEach(() => vi.useRealTimers())

describe('useEmailTemplates', () => {
  it('traduz a lista, abre o primeiro modelo e pede a prévia ao backend', async () => {
    const emails = useEmailTemplates('ed-1')
    await emails.load()

    expect(emails.templates.value[0]).toMatchObject({ id: 'order_confirmed', actionLabel: 'Ver meu ingresso', lastSentAt: '2027-03-10T15:42:00Z' })
    expect(emails.selected.value?.id).toBe('order_confirmed')
    expect(emails.draft.value).toEqual({ subject: 'Seu ingresso para o {edicao}', body: 'Oi, {nome}!\n\nPedido {pedido}.' })
    await vi.waitFor(() => expect(emails.previewState.value).toBe('ready'))
    expect(communicationService.preview).toHaveBeenCalledWith('ed-1', 'order_confirmed', emails.draft.value)
    expect(emails.preview.value?.subject).toBe('Seu ingresso para o PHPeste 2027')
  })

  it('variável que o modelo não conhece bloqueia salvar, testar e a prévia, com a lista das válidas', async () => {
    const emails = useEmailTemplates('ed-1')
    await emails.load()
    vi.mocked(communicationService.preview).mockClear()
    vi.useFakeTimers()

    emails.edit('body', 'Oi, {nome}! Seu {ingresso}.')
    expect(emails.errors.value.body).toBe('Variável desconhecida no modelo: {ingresso}. Use só {nome}, {edicao}, {pedido}, {data}.')
    expect(emails.valid.value).toBe(false)
    await vi.advanceTimersByTimeAsync(500)
    expect(emails.previewState.value).toBe('blocked')
    expect(communicationService.preview).not.toHaveBeenCalled()

    expect(await emails.save()).toMatchObject({ ok: false, message: expect.stringContaining('{ingresso}') })
    expect(await emails.sendTest()).toMatchObject({ ok: false })
    expect(communicationService.save).not.toHaveBeenCalled()
    expect(communicationService.sendTest).not.toHaveBeenCalled()
  })

  it('a prévia espera a pessoa parar de digitar e ignora resposta velha', async () => {
    const emails = useEmailTemplates('ed-1')
    await emails.load()
    await vi.waitFor(() => expect(emails.previewState.value).toBe('ready'))
    vi.mocked(communicationService.preview).mockClear()
    vi.useFakeTimers()

    emails.edit('subject', 'A {edicao}')
    emails.edit('subject', 'AB {edicao}')
    await vi.advanceTimersByTimeAsync(399)
    expect(communicationService.preview).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(1)
    expect(communicationService.preview).toHaveBeenCalledTimes(1)
    expect(communicationService.preview).toHaveBeenLastCalledWith('ed-1', 'order_confirmed', { subject: 'AB {edicao}', body: 'Oi, {nome}!\n\nPedido {pedido}.' })
  })

  it('salva, marca como personalizado e mostra o erro 422 do backend no campo', async () => {
    const emails = useEmailTemplates('ed-1')
    await emails.load()
    emails.edit('subject', 'Pronto: {edicao}')
    expect(emails.dirty.value).toBe(true)

    vi.mocked(communicationService.save).mockResolvedValueOnce({ data: templatePayload('order_confirmed', 'Confirmação de compra', { subject: 'Pronto: {edicao}', customized: true }) })
    expect(await emails.save()).toEqual({ ok: true })
    expect(emails.selected.value).toMatchObject({ customized: true, subject: 'Pronto: {edicao}' })
    expect(emails.dirty.value).toBe(false)

    emails.edit('subject', 'De novo {edicao}')
    vi.mocked(communicationService.save).mockRejectedValueOnce(new CommunicationError('x', 422, { subject: ['O assunto pode ter no máximo 200 caracteres.'] }))
    expect(await emails.save()).toMatchObject({ ok: false, fieldErrors: { subject: 'O assunto pode ter no máximo 200 caracteres.' } })
    expect(emails.errors.value.subject).toBe('O assunto pode ter no máximo 200 caracteres.')
    emails.edit('subject', 'Curto {edicao}')
    expect(emails.errors.value.subject).toBeNull()
  })

  it('teste vai com o texto da tela, sem salvar, e devolve o e-mail de quem pediu', async () => {
    const emails = useEmailTemplates('ed-1')
    await emails.load()
    emails.edit('body', 'Rascunho para {nome}')
    vi.mocked(communicationService.sendTest).mockResolvedValue({ data: { id: 'd-1', to_email: 'rita@php-pb.net', status: 'queued', status_label: 'Na fila' } })

    expect(await emails.sendTest()).toEqual({ ok: true, email: 'rita@php-pb.net' })
    expect(communicationService.sendTest).toHaveBeenCalledWith('ed-1', 'order_confirmed', { subject: 'Seu ingresso para o {edicao}', body: 'Rascunho para {nome}' })
    expect(communicationService.save).not.toHaveBeenCalled()

    vi.mocked(communicationService.sendTest).mockRejectedValue(new CommunicationError('x', 429))
    expect(await emails.sendTest()).toMatchObject({ ok: false, message: 'Muitos testes seguidos. Espere um minuto e tente de novo.' })
  })

  it('restaurar o padrão troca o texto e tira o selo de personalizado', async () => {
    const emails = useEmailTemplates('ed-1')
    await emails.load()
    emails.select('event_reminder')
    vi.mocked(communicationService.restore).mockResolvedValue({ data: templatePayload('event_reminder', 'Antes do evento', { subject: 'Falta uma semana para o {edicao}', customized: false }) })

    expect(await emails.restore()).toEqual({ ok: true })
    expect(emails.selected.value).toMatchObject({ customized: false })
    expect(emails.draft.value.subject).toBe('Falta uma semana para o {edicao}')
  })

  it('falha ao carregar vira estado de erro', async () => {
    vi.mocked(communicationService.list).mockRejectedValue(new CommunicationError('x', 500))
    const emails = useEmailTemplates('ed-1')
    await emails.load()
    expect(emails.state.value).toBe('error')
  })
})
