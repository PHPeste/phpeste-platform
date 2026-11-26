const tokenKey = (orderId: string) => `phpeste.checkout.order.${orderId}`
const cartKey = (editionId: string) => `phpeste.checkout.cart.${editionId}`

export interface SavedCart { quantities: Record<string, number>, coupon: string }

function read(key: string): string | null {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

function write(key: string, value: string) {
  try {
    localStorage.setItem(key, value)
  } catch {
    return
  }
}

export const saveOrderToken = (orderId: string, token: string) => write(tokenKey(orderId), token)

export const orderToken = (orderId: string) => read(tokenKey(orderId))

export const saveCart = (editionId: string, cart: SavedCart) => write(cartKey(editionId), JSON.stringify(cart))

export function savedCart(editionId: string): SavedCart | null {
  try {
    const value = JSON.parse(read(cartKey(editionId)) ?? 'null') as SavedCart | null
    return value && typeof value.quantities === 'object' ? { quantities: value.quantities, coupon: typeof value.coupon === 'string' ? value.coupon : '' } : null
  } catch {
    return null
  }
}
