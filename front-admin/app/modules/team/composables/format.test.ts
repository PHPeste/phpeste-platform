import { describe, expect, it } from 'vitest'
import { initials, lastSeenLabel } from './format'

describe('initials', () => {
  it('usa nome e sobrenome, ou o e-mail quando não há nome', () => {
    expect(initials('Mariana Sales', 'm@x.dev')).toBe('MS')
    expect(initials('Lívia', 'l@x.dev')).toBe('LÍ')
    expect(initials(null, 'joao.paulo@x.dev')).toBe('JP')
  })
})

describe('lastSeenLabel', () => {
  const now = new Date(2027, 2, 10, 15, 0)

  it('descreve o último acesso como o design', () => {
    expect(lastSeenLabel(null, now)).toBe('nunca')
    expect(lastSeenLabel(new Date(2027, 2, 10, 14, 58).toISOString(), now)).toBe('agora')
    expect(lastSeenLabel(new Date(2027, 2, 10, 14, 20).toISOString(), now)).toBe('hoje, 14:20')
    expect(lastSeenLabel(new Date(2027, 2, 9, 23, 0).toISOString(), now)).toBe('ontem')
    expect(lastSeenLabel(new Date(2027, 2, 8, 9, 0).toISOString(), now)).toBe('2 dias atrás')
  })
})
