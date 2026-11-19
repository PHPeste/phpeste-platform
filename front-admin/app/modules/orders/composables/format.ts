const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })

/** 15000 → "R$ 150,00". */
export const formatCents = (cents: number) => brl.format(cents / 100)

/** "1.234,56", "150" ou "R$ 150,00" → centavos. Texto inválido → null. */
export function parseReais(text: string): number | null {
  const clean = text.replace(/R\$|\s/g, '').trim()
  if (clean === '') return null
  if (!/^\d{1,3}(\.\d{3})*(,\d{1,2})?$|^\d+(,\d{1,2})?$/.test(clean)) return null
  const [integer = '0', decimals = ''] = clean.replace(/\./g, '').split(',')
  return Number(integer) * 100 + Number(decimals.padEnd(2, '0'))
}

/** Centavos → "1.234,56" para o campo de edição. */
export const centsToInput = (cents: number) => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cents / 100)

const dateTime = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })

/** "10 de jun., 09:00". */
export const formatDateTime = (iso: string | null) => (iso ? dateTime.format(new Date(iso)) : '')
