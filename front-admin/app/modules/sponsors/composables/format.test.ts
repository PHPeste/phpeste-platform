import { describe, expect, it } from 'vitest'
import { formatCents, formatSize, initials, parseReais } from './format'

const flat = (text: string) => text.replace(/\s/g, ' ')

describe('format', () => {
  it('valor redondo sem centavos, quebrado com centavos', () => {
    expect(flat(formatCents(800_000))).toBe('R$ 8.000')
    expect(flat(formatCents(150_050))).toBe('R$ 1.500,50')
  })

  it('lê reais digitados em centavos', () => {
    expect(parseReais('8.000')).toBe(800_000)
    expect(parseReais('R$ 1.500,5')).toBe(150_050)
    expect(parseReais('abc')).toBeNull()
  })

  it('iniciais e tamanho do arquivo', () => {
    expect(initials('Cloud Sul Tecnologia')).toBe('CS')
    expect(formatSize(2048)).toBe('2 KB')
    expect(formatSize(3 * 1024 * 1024)).toBe('3,0 MB')
  })
})
