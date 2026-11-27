import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '@/stores/authStore'
import accountService from '../services/accountService'
import { AccountError } from '../services/accountError'
import { ticketPayload } from './fixtures'
import { saveTickets, savedTickets } from './storage'
import { translateTicket } from './translate'
import { badgeErrors, transferErrors, useTicket } from './useTicket'

vi.mock('../services/accountService', () => ({ default: { ticket: vi.fn(), update: vi.fn(), transfer: vi.fn(), resend: vi.fn() } }))

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  localStorage.clear()
  useAuthStore().setSession({ id: 'u-1', name: 'Ada', email: 'ada@x.dev', avatarUrl: null }, 'tok')
})

async function loaded() {
  vi.mocked(accountService.ticket).mockResolvedValue({ data: { ...ticketPayload(), transfers: [{ from: { name: 'Grace', email: 'grace@x.dev' }, to: { name: 'Ada Lovelace', email: 'ada@x.dev' }, channel: 'holder', at: '2027-06-11T10:00:00Z' }] } })
  const detail = useTicket('ed-1', 'tk-1')
  await detail.load()
  return detail
}

describe('validações', () => {
  it('confere nome, tamanho dos campos e o e-mail de destino', () => {
    expect(badgeErrors({ holderName: ' ', badgeName: 'x'.repeat(61), company: '', tshirtSize: '', accessibility: '' })).toEqual({ holderName: 'Informe o nome de quem vai usar o ingresso.', badgeName: 'Use até 60 caracteres.' })
    expect(transferErrors('B', 'nao-e-email', 'ada@x.dev')).toEqual({ name: 'Informe o nome de quem vai receber.', email: 'Informe um e-mail válido.' })
    expect(transferErrors('Bia', 'ADA@x.dev', 'ada@x.dev')).toEqual({ email: 'O ingresso já está com este e-mail.' })
  })
})

describe('useTicket', () => {
  it('salva o crachá com campos vazios como nulos e mantém o histórico', async () => {
    const detail = await loaded()
    Object.assign(detail.form, { holderName: ' Ada Lovelace ', badgeName: ' Ada ', company: '  ', tshirtSize: 'M', accessibility: '' })
    vi.mocked(accountService.update).mockResolvedValue({ data: ticketPayload({ holder: { name: 'Ada Lovelace', email: 'ada@x.dev', badge_name: 'Ada', company: null, tshirt_size: 'M', accessibility: null }, missing: [] }) })

    expect(await detail.save()).toEqual({ ok: true })
    expect(accountService.update).toHaveBeenCalledWith('ed-1', 'tk-1', { holder_name: 'Ada Lovelace', badge_name: 'Ada', company: null, tshirt_size: 'M', accessibility: null })
    expect(detail.ticket.value).toMatchObject({ badgeName: 'Ada', tshirtSize: 'M', missing: [] })
    expect(detail.ticket.value?.transfers).toHaveLength(1)
    expect(savedTickets('ed-1', 'u-1')?.tickets[0]?.badgeName).toBe('Ada')
  })

  it('traz o erro de campo do backend para o nome do formulário', async () => {
    const detail = await loaded()
    vi.mocked(accountService.update).mockRejectedValue(new AccountError('invalid', 422, { tshirt_size: ['Tamanho inválido.'] }))
    expect(await detail.save()).toEqual({ ok: false, message: 'Tamanho inválido.', fieldErrors: { tshirtSize: 'Tamanho inválido.' } })
  })

  it('transferir apaga o QR da tela e da cópia guardada na hora', async () => {
    const detail = await loaded()
    expect(savedTickets('ed-1', 'u-1')?.tickets).toHaveLength(1)
    vi.mocked(accountService.transfer).mockResolvedValue({ data: ticketPayload() })

    expect(await detail.transfer(' Bia Lima ', 'Bia@X.dev')).toEqual({ ok: true })
    expect(accountService.transfer).toHaveBeenCalledWith('ed-1', 'tk-1', { name: 'Bia Lima', email: 'Bia@X.dev' })
    expect(detail.state.value).toBe('transferred')
    expect(detail.ticket.value).toBeNull()
    expect(detail.transferredTo.value).toEqual({ name: 'Bia Lima', email: 'bia@x.dev' })
    expect(savedTickets('ed-1', 'u-1')?.tickets).toEqual([])
  })

  it('não dispara a transferência duas vezes enquanto a primeira está a caminho', async () => {
    const detail = await loaded()
    vi.mocked(accountService.transfer).mockReturnValue(new Promise(() => {}))
    void detail.transfer('Bia Lima', 'bia@x.dev')
    await detail.transfer('Bia Lima', 'bia@x.dev')
    expect(accountService.transfer).toHaveBeenCalledTimes(1)
  })

  it('sem internet abre o ingresso guardado; ingresso que saiu da conta some da cópia', async () => {
    saveTickets('ed-1', 'u-1', [translateTicket(ticketPayload())])
    vi.mocked(accountService.ticket).mockRejectedValueOnce(new AccountError('offline', null))
    const offline = useTicket('ed-1', 'tk-1')
    await offline.load()
    expect(offline.state.value).toBe('ready')
    expect(offline.offline.value).toBe(true)
    expect(offline.ticket.value?.qr).toBe('PHT1.tk-1.1.abc')

    vi.mocked(accountService.ticket).mockRejectedValueOnce(new AccountError('missing', 404))
    const gone = useTicket('ed-1', 'tk-1')
    await gone.load()
    expect(gone.state.value).toBe('not-found')
    expect(savedTickets('ed-1', 'u-1')?.tickets).toEqual([])
  })
})
