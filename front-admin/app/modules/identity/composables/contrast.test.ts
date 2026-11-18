import { describe, expect, it } from 'vitest'
import { contrastRatio, evaluateContrast } from './contrast'

const tokens = [{ id: 'ink', label: 'Texto' }, { id: 'bg', label: 'Fundo' }, { id: 'brand', label: 'Marca' }]

describe('contrastRatio', () => {
  it('calcula a razão WCAG como o backend', () => {
    expect(contrastRatio('#000000', '#FFFFFF')).toBe(21)
    expect(contrastRatio('#3A1607', '#FBF4E4')).toBe(14.77)
    expect(contrastRatio('#FFFFFF', '#2E7DD1')).toBe(4.23)
  })
})

describe('evaluateContrast', () => {
  it('classifica cada par em AAA, AA ou abaixo do mínimo', () => {
    const results = evaluateContrast(
      { ink: '#3A1607', bg: '#FBF4E4', brand: '#C9B48A' },
      [{ foreground: 'ink', background: 'bg', minimum: 4.5 }, { foreground: 'brand', background: 'bg', minimum: 3 }, { foreground: 'ink', background: 'brand', minimum: 4.5 }],
      tokens,
    )

    expect(results.map((result) => [result.label, result.level, result.passes])).toEqual([
      ['Texto sobre Fundo', 'AAA', true],
      ['Marca sobre Fundo', 'fail', false],
      ['Texto sobre Marca', 'AAA', true],
    ])
  })

  it('ignora par com cor fora do formato, que já tem seu próprio erro', () => {
    expect(evaluateContrast({ ink: '#3A16', bg: '#FBF4E4' }, [{ foreground: 'ink', background: 'bg', minimum: 4.5 }], tokens)).toEqual([])
  })
})
