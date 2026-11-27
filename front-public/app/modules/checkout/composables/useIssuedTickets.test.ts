import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import checkoutService from '../services/checkoutService'
import { useIssuedTickets } from './useIssuedTickets'
import { saveOrderToken } from './storage'

vi.mock('../services/checkoutService', () => ({ default: { tickets: vi.fn() } }))

const ticket = { id: 'tk-1', code: 'K7QM-4XTD', ticket_type_name: 'Inteira', batch_name: '1º lote', holder: { name: 'Ada Lovelace', email: 'ada@x.dev' }, qr: 'PHT1.tk-1.1.abc' }

beforeEach(() => {
  vi.clearAllMocks()
  localStorage.clear()
  saveOrderToken('o-1', 'tok')
})

afterEach(() => vi.useRealTimers())

describe('useIssuedTickets', () => {
  it('tenta de novo enquanto os ingressos não aparecem', async () => {
    vi.useFakeTimers()
    vi.mocked(checkoutService.tickets).mockResolvedValueOnce({ data: [] }).mockResolvedValueOnce({ data: [ticket] })
    const issued = useIssuedTickets('ed-1', 'o-1')
    await issued.load()
    expect(issued.state.value).toBe('loading')

    await vi.advanceTimersByTimeAsync(1000)
    expect(issued.state.value).toBe('ready')
    expect(issued.tickets.value).toEqual([{ id: 'tk-1', code: 'K7QM-4XTD', typeName: 'Inteira', holderName: 'Ada Lovelace', qr: 'PHT1.tk-1.1.abc' }])
  })
})
