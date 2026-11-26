import { describe, expect, it } from 'vitest'
import { formatCents, formatCountdown, isCpf, isEmail, maskCpf } from './format'

const flat = (text: string) => text.replace(/\s/g, ' ')

describe('format', () => {
  it('reais, contagem e e-mail', () => {
    expect(flat(formatCents(15000))).toBe('R$ 150,00')
    expect(formatCountdown(845)).toBe('14:05')
    expect(formatCountdown(-3)).toBe('00:00')
    expect(isEmail('ada@x.dev')).toBe(true)
    expect(isEmail('ada@x')).toBe(false)
  })

  it('CPF: confere os dígitos e põe a máscara enquanto digita', () => {
    expect(isCpf('529.982.247-25')).toBe(true)
    expect(isCpf('529.982.247-24')).toBe(false)
    expect(isCpf('111.111.111-11')).toBe(false)
    expect(maskCpf('5299822')).toBe('529.982.2')
    expect(maskCpf('52998224725999')).toBe('529.982.247-25')
  })
})
