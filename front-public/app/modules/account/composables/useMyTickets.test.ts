import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '@/stores/authStore'
import accountService from '../services/accountService'
import { AccountError } from '../services/accountError'
import { ticketPayload } from './fixtures'
import { savedTickets } from './storage'
import { useMyTickets } from './useMyTickets'

vi.mock('../services/accountService', () => ({ default: { tickets: vi.fn() } }))

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  localStorage.clear()
  useAuthStore().setSession({ id: 'u-1', name: 'Ada', email: 'ada@x.dev', avatarUrl: null }, 'tok')
})

describe('useMyTickets', () => {
  it('guarda no aparelho os ingressos carregados, com o QR', async () => {
    vi.mocked(accountService.tickets).mockResolvedValue({ data: [ticketPayload()], meta: { email_verified: true } })
    const mine = useMyTickets('ed-1')
    await mine.load()

    expect(mine.state.value).toBe('ready')
    expect(mine.tickets.value[0]).toMatchObject({ holderName: 'Ada Lovelace', qr: 'PHT1.tk-1.1.abc', missing: ['badge_name', 'tshirt_size'] })
    expect(savedTickets('ed-1', 'u-1')?.tickets[0]?.qr).toBe('PHT1.tk-1.1.abc')
  })

  it('sem internet, mostra os ingressos guardados e quando foram salvos', async () => {
    vi.mocked(accountService.tickets).mockResolvedValueOnce({ data: [ticketPayload()], meta: { email_verified: true } })
    await useMyTickets('ed-1').load()

    vi.mocked(accountService.tickets).mockRejectedValueOnce(new AccountError('offline', null))
    const offline = useMyTickets('ed-1')
    await offline.load()

    expect(offline.state.value).toBe('ready')
    expect(offline.tickets.value[0]?.qr).toBe('PHT1.tk-1.1.abc')
    expect(offline.savedAt.value).not.toBeNull()
  })

  it('erro do servidor não usa a cópia guardada, e a cópia é de cada pessoa', async () => {
    vi.mocked(accountService.tickets).mockResolvedValueOnce({ data: [ticketPayload()], meta: { email_verified: true } })
    await useMyTickets('ed-1').load()

    vi.mocked(accountService.tickets).mockRejectedValueOnce(new AccountError('boom', 500))
    const failed = useMyTickets('ed-1')
    await failed.load()
    expect(failed.state.value).toBe('error')

    useAuthStore().setSession({ id: 'u-2', name: 'Bia', email: 'bia@x.dev', avatarUrl: null }, 'tok-2')
    vi.mocked(accountService.tickets).mockRejectedValueOnce(new AccountError('offline', null))
    const other = useMyTickets('ed-1')
    await other.load()
    expect(other.state.value).toBe('error')
  })

  it('avisa quando o e-mail ainda não foi confirmado', async () => {
    vi.mocked(accountService.tickets).mockResolvedValue({ data: [], meta: { email_verified: false } })
    const mine = useMyTickets('ed-1')
    await mine.load()
    expect(mine.emailVerified.value).toBe(false)
  })
})
