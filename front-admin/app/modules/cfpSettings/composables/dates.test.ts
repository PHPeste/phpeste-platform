import { describe, expect, it } from 'vitest'
import { endOfDay, longDay, startOfDay, toDay } from './dates'

describe('datas do CFP no horário de Brasília', () => {
  it('abre à 00:00 e fecha às 23:59 do dia', () => {
    expect(startOfDay('2027-02-01')).toBe('2027-02-01T03:00:00.000Z')
    expect(endOfDay('2027-04-15')).toBe('2027-04-16T02:59:59.000Z')
    expect(startOfDay('')).toBeNull()
  })

  it('mostra o dia certo mesmo quando o UTC já virou', () => {
    expect(toDay('2027-04-16T02:59:59Z')).toBe('2027-04-15')
    expect(toDay(null)).toBe('')
    expect(longDay('2027-04-15')).toBe('15 de abril')
  })
})
