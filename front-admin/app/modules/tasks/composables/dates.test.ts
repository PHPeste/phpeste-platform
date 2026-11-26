import { describe, expect, it } from 'vitest'
import { daysLate, dueSoon, initials } from './dates'

const now = new Date(2027, 2, 10, 15, 0)

describe('dates', () => {
  it('conta os dias de atraso só pela data', () => {
    expect(daysLate('2027-03-07', now)).toBe(3)
    expect(daysLate('2027-03-10', now)).toBe(0)
    expect(daysLate(null, now)).toBe(0)
  })

  it('"em breve" é de hoje até 7 dias', () => {
    expect(dueSoon('2027-03-10', now)).toBe(true)
    expect(dueSoon('2027-03-17', now)).toBe(true)
    expect(dueSoon('2027-03-18', now)).toBe(false)
    expect(dueSoon('2027-03-09', now)).toBe(false)
  })

  it('iniciais', () => {
    expect(initials('Mariana Sales Lima')).toBe('MS')
  })
})
