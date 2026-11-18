const TIME_ZONE = 'America/Sao_Paulo'
const OFFSET = '-03:00'

export function toDay(iso: string | null): string {
  if (!iso) return ''
  return new Intl.DateTimeFormat('en-CA', { timeZone: TIME_ZONE, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(iso))
}

export function startOfDay(day: string): string | null {
  return day ? new Date(`${day}T00:00:00${OFFSET}`).toISOString() : null
}

export function endOfDay(day: string): string | null {
  return day ? new Date(`${day}T23:59:59${OFFSET}`).toISOString() : null
}

export function longDay(day: string): string {
  return new Date(`${day}T12:00:00${OFFSET}`).toLocaleDateString('pt-BR', { timeZone: TIME_ZONE, day: 'numeric', month: 'long' })
}
