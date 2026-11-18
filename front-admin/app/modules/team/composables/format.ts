import locale from '../locales/pt-br'

export function initials(name: string | null, email: string): string {
  const source = (name ?? '').trim() || email
  const words = source.split(/[\s@._-]+/).filter(Boolean)
  return ((words[0]?.[0] ?? '') + (words.length > 1 ? (words[1]?.[0] ?? '') : (words[0]?.[1] ?? ''))).toUpperCase()
}

export function lastSeenLabel(iso: string | null, now: Date = new Date()): string {
  if (!iso) return locale.never

  const seen = new Date(iso)
  const minutes = (now.getTime() - seen.getTime()) / 60000
  if (minutes < 5) return locale.now

  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const days = Math.floor((startOfToday.getTime() - new Date(seen.getFullYear(), seen.getMonth(), seen.getDate()).getTime()) / 86400000)

  if (days <= 0) return locale.today.replace('{hora}', seen.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }))
  if (days === 1) return locale.yesterday
  return locale.daysAgo.replace('{n}', String(days))
}

export function shortDate(iso: string): string {
  return new Date(iso).toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' }).replace('.', '')
}
