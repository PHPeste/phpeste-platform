const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })

/** 15000 → "R$ 150,00" (com separador de milhar). */
export const formatCents = (cents: number) => brl.format(cents / 100)

/** "1.234,56", "1234,5", "150" ou "R$ 150,00" → centavos. Texto inválido → null. */
export function parseReais(text: string): number | null {
  const clean = text.replace(/R\$|\s/g, '').trim()
  if (clean === '') return null
  if (!/^\d{1,3}(\.\d{3})*(,\d{1,2})?$|^\d+(,\d{1,2})?$/.test(clean)) return null
  const [integer = '0', decimals = ''] = clean.replace(/\./g, '').split(',')
  return Number(integer) * 100 + Number(decimals.padEnd(2, '0'))
}

/** Centavos → "1.234,56" para o campo de edição. */
export const centsToInput = (cents: number) => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cents / 100)

/** Mesma conta do backend (`Split::fee`): arredonda a taxa para cima. */
export const feeFor = (cents: number, basisPoints: number) => (basisPoints === 0 || cents === 0 ? 0 : Math.ceil((cents * basisPoints) / 10_000))

/**
 * Quanto quem compra paga e quanto a edição recebe por ingresso.
 * Com a taxa repassada, ela soma ao preço; senão, sai do que a edição recebe.
 */
export function netFor(cents: number, basisPoints: number, passFeeToBuyer: boolean) {
  const fee = feeFor(cents, basisPoints)
  return passFeeToBuyer ? { buyerPays: cents + fee, fee, received: cents } : { buyerPays: cents, fee, received: cents - fee }
}

/** ISO (UTC) → valor de `<input type="datetime-local">` no fuso do navegador. */
export function isoToLocal(iso: string | null): string {
  if (!iso) return ''
  const date = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** Valor do `datetime-local` → ISO (UTC); vazio → null. */
export const localToIso = (local: string) => (local ? new Date(local).toISOString() : null)

const dateTime = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })

/** "10 de jun., 09:00" para listas. */
export const formatDateTime = (iso: string | null) => (iso ? dateTime.format(new Date(iso)) : null)
