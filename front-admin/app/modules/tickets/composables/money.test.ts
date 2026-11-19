import { describe, expect, it } from 'vitest'
import { centsToInput, feeFor, formatCents, netFor, parseReais } from './money'

describe('money', () => {
  it('formata em reais com separador de milhar', () => {
    expect(formatCents(123456)).toBe('R$ 1.234,56')
    expect(centsToInput(15000)).toBe('150,00')
  })

  it('lê o valor digitado em reais', () => {
    expect(parseReais('150')).toBe(15000)
    expect(parseReais('150,5')).toBe(15050)
    expect(parseReais('1.234,56')).toBe(123456)
    expect(parseReais('R$ 0,99')).toBe(99)
    expect(parseReais('0')).toBe(0)
    expect(parseReais('')).toBeNull()
    expect(parseReais('15,999')).toBeNull()
    expect(parseReais('abc')).toBeNull()
  })

  it('calcula a taxa como o backend (arredonda para cima) e o líquido', () => {
    expect(feeFor(15000, 499)).toBe(749)
    expect(feeFor(0, 499)).toBe(0)
    expect(netFor(15000, 499, false)).toEqual({ buyerPays: 15000, fee: 749, received: 14251 })
    expect(netFor(15000, 499, true)).toEqual({ buyerPays: 15749, fee: 749, received: 15000 })
  })
})
