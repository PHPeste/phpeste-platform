import { describe, expect, it } from 'vitest'
import { formatLastSent, placeholders, unknownVariables } from './format'

describe('variáveis', () => {
  it('reconhece só chaves com letras minúsculas e sublinhado, sem repetir', () => {
    expect(placeholders('Oi {nome}, {nome}! {Edicao} { data } {pedido_id}')).toEqual(['nome', 'pedido_id'])
  })

  it('aponta as que o modelo não conhece', () => {
    expect(unknownVariables('Oi {nome}, pedido {pedidos}', ['nome', 'pedido'])).toEqual(['pedidos'])
    expect(unknownVariables('Sem variável', ['nome'])).toEqual([])
  })
})

describe('formatLastSent', () => {
  const now = new Date(2027, 2, 10, 18, 0)

  it('hoje e ontem com a hora', () => {
    expect(formatLastSent(new Date(2027, 2, 10, 15, 42).toISOString(), now)).toBe('hoje, 15:42')
    expect(formatLastSent(new Date(2027, 2, 9, 9, 5).toISOString(), now)).toBe('ontem, 09:05')
  })

  it('outros dias do ano com data e hora; de outro ano, só a data', () => {
    expect(formatLastSent(new Date(2027, 0, 3, 8, 0).toISOString(), now)).toBe('3 de jan., 08:00')
    expect(formatLastSent(new Date(2026, 10, 20, 8, 0).toISOString(), now)).toBe('20 de nov. de 2026')
  })
})
