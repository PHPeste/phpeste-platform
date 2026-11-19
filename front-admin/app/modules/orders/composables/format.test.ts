import { describe, expect, it } from 'vitest'
import { centsToInput, formatCents, parseReais } from './format'

describe('format', () => {
  it('converte reais digitados em centavos e de volta', () => {
    expect(parseReais('150')).toBe(15000)
    expect(parseReais('1.234,5')).toBe(123450)
    expect(parseReais('R$ 75,00')).toBe(7500)
    expect(parseReais('abc')).toBeNull()
    expect(parseReais('')).toBeNull()
    expect(centsToInput(123450)).toBe('1.234,50')
    expect(formatCents(30000).replace(/\s/g, ' ')).toBe('R$ 300,00')
  })
})
