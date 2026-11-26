const dayShort = new Intl.DateTimeFormat('pt-BR', { day: 'numeric', month: 'short', timeZone: 'UTC' })

/** "2027-08-15" → "15 de ago.". */
export const formatShort = (date: string | null) => (date ? dayShort.format(new Date(`${date}T00:00:00Z`)) : '')

/** "Mariana Sales" → "MS". */
export const initials = (name: string) => name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('')

function todayUtc(now = new Date()): number {
  return Date.UTC(now.getFullYear(), now.getMonth(), now.getDate())
}

/** Dias desde o prazo, contando só a data. */
export function daysLate(date: string | null, now = new Date()): number {
  if (!date) return 0
  return Math.max(0, Math.round((todayUtc(now) - Date.parse(`${date}T00:00:00Z`)) / 86_400_000))
}

/** Prazo entre hoje e os próximos 7 dias. */
export function dueSoon(date: string | null, now = new Date()): boolean {
  if (!date) return false
  const diff = (Date.parse(`${date}T00:00:00Z`) - todayUtc(now)) / 86_400_000
  return diff >= 0 && diff <= 7
}
