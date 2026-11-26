import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import checkoutService from '../services/checkoutService'
import { CheckoutError } from '../services/checkoutError'
import { useOrder } from './useOrder'
import { saveOrderToken } from './storage'
import { orderPayload } from './fixtures'

vi.mock('../services/checkoutService', () => ({ default: { catalog: vi.fn(), checkCoupon: vi.fn(), placeOrder: vi.fn(), order: vi.fn(), cancel: vi.fn() } }))

beforeEach(() => {
  vi.clearAllMocks()
  localStorage.clear()
})

afterEach(() => vi.useRealTimers())

describe('useOrder', () => {
  it('sem o token deste navegador o pedido não é encontrado', async () => {
    const order = useOrder('ed-1', 'o-1')
    await order.load()
    expect(order.state.value).toBe('not_found')
    expect(checkoutService.order).not.toHaveBeenCalled()
  })

  it('conta o tempo da reserva e marca expirada quando zera', async () => {
    saveOrderToken('o-1', 'tok')
    vi.mocked(checkoutService.order).mockResolvedValue({ data: orderPayload() })
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2027-03-10T12:28:00Z'))
    const order = useOrder('ed-1', 'o-1')
    await order.load()

    expect(checkoutService.order).toHaveBeenCalledWith('ed-1', 'o-1', 'tok')
    expect(order.order.value).toMatchObject({ number: 42, feeCents: 1080, totalCents: 28080 })
    expect(order.secondsLeft.value).toBe(120)
    expect(order.lapsed.value).toBeNull()

    await vi.advanceTimersByTimeAsync(120_000)
    expect(order.secondsLeft.value).toBe(0)
    expect(order.lapsed.value).toBe('expired')
    order.stop()
  })

  it('expirado no backend ou cancelado pela pessoa', async () => {
    saveOrderToken('o-1', 'tok')
    vi.mocked(checkoutService.order).mockResolvedValue({ data: orderPayload({ status: 'expired' }) })
    const expired = useOrder('ed-1', 'o-1')
    await expired.load()
    expect(expired.lapsed.value).toBe('expired')

    vi.mocked(checkoutService.order).mockResolvedValue({ data: orderPayload({ expires_at: '2999-01-01T00:00:00Z' }) })
    vi.mocked(checkoutService.cancel).mockResolvedValue({ data: orderPayload({ status: 'cancelled' }) })
    const order = useOrder('ed-1', 'o-1')
    await order.load()
    expect(await order.cancel()).toBeNull()
    expect(order.lapsed.value).toBe('cancelled')
  })

  it('404 vira pedido não encontrado; outros erros, erro com tentar de novo', async () => {
    saveOrderToken('o-1', 'tok')
    vi.mocked(checkoutService.order).mockRejectedValueOnce(new CheckoutError('x', 404))
    const order = useOrder('ed-1', 'o-1')
    await order.load()
    expect(order.state.value).toBe('not_found')

    vi.mocked(checkoutService.order).mockRejectedValueOnce(new CheckoutError('x', 500))
    await order.load()
    expect(order.state.value).toBe('error')
  })
})
