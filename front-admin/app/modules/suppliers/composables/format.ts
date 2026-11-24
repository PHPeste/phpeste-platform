const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
const brlWhole = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 0, maximumFractionDigits: 0 })

/** 800000 → "R$ 8.000"; 150050 → "R$ 1.500,50". */
export const formatCents = (cents: number) => (cents % 100 === 0 ? brlWhole : brl).format(cents / 100)

/** "1.234,56", "8000" ou "R$ 8.000,00" → centavos. Texto inválido → null. */
export function parseReais(text: string): number | null {
  const clean = text.replace(/R\$|\s/g, '').trim()
  if (clean === '') return null
  if (!/^\d{1,3}(\.\d{3})*(,\d{1,2})?$|^\d+(,\d{1,2})?$/.test(clean)) return null
  const [integer = '0', decimals = ''] = clean.replace(/\./g, '').split(',')
  return Number(integer) * 100 + Number(decimals.padEnd(2, '0'))
}

/** Centavos → "8.000,00" para o campo de edição. */
export const centsToInput = (cents: number) => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cents / 100)

const day = new Intl.DateTimeFormat('pt-BR', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' })
const dayShort = new Intl.DateTimeFormat('pt-BR', { day: 'numeric', month: 'short' })
const dateTime = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })

/** "2027-06-01" → "1 de jun. de 2027". */
export const formatDay = (date: string | null) => (date ? day.format(new Date(`${date}T00:00:00Z`)) : '')

/** ISO → "12 de out.". */
export const formatShort = (iso: string | null) => (iso ? dayShort.format(new Date(iso)) : '')

/** ISO → "10 de jun., 09:00". */
export const formatDateTime = (iso: string | null) => (iso ? dateTime.format(new Date(iso)) : '')

/** "Cloud Sul" → "CS". */
export const initials = (name: string) => name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('')

/** 2048 → "2 KB". */
export function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`
}

/** Data de hoje no fuso de quem usa, "AAAA-MM-DD". */
export function today(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}
