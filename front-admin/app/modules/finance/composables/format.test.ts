import { describe, expect, it } from 'vitest'
import { formatCents, parseReais } from './format'

const flat = (text: string) => text.replace(/\s/g, ' ')

describe('format', () => {
  it('sempre em reais com separador de milhar', () => {
    expect(flat(formatCents(2_070_000))).toBe('R$ 20.700')
    expect(flat(formatCents(123_456_789))).toBe('R$ 1.234.567,89')
    expect(flat(formatCents(747))).toBe('R$ 7,47')
    expect(flat(formatCents(-70_000))).toBe('-R$ 700')
  })

  it('lê reais digitados', () => {
    expect(parseReais('18.000')).toBe(1_800_000)
    expect(parseReais('1.500,5')).toBe(150_050)
    expect(parseReais('mil')).toBeNull()
  })
})
