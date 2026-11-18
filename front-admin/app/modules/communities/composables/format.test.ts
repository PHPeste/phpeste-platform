import { describe, expect, it } from 'vitest'
import { formatEditionDates, initials, slugify, statusTone } from './format'

describe('formatEditionDates', () => {
  it.each([
    ['2027-10-08', '2027-10-09', '8 e 9 out 2027'],
    ['2027-10-08', '2027-10-10', '8 a 10 out 2027'],
    ['2027-10-08', null, '8 out 2027'],
    ['2027-09-30', '2027-10-02', '30 set a 2 out 2027'],
    ['2027-12-30', '2028-01-02', '30 dez 2027 a 2 jan 2028'],
  ])('%s → %s = %s', (start, end, expected) => {
    expect(formatEditionDates(start, end)).toBe(expected)
  })

  it('sem data de início não inventa nada', () => {
    expect(formatEditionDates(null, null)).toBeNull()
  })
})

describe('helpers', () => {
  it('gera endereço curto a partir do nome', () => {
    expect(slugify('PHPeste São Luís 2028!')).toBe('phpeste-sao-luis-2028')
  })

  it('mapeia a etapa para o tom do selo', () => {
    expect([statusTone('draft'), statusTone('cfp_open'), statusTone('sales_open'), statusTone('closed')]).toEqual(['info', 'warn', 'ok', 'neutral'])
  })

  it('usa as letras do fim do endereço como avatar', () => {
    expect(initials('php-pb', 'PHP-PB')).toBe('PB')
    expect(initials('phpeste', 'PHPeste')).toBe('PH')
  })
})
