import type { BadgeTone } from '../types'

const MONTHS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']

function parse(date: string): { day: number, month: number, year: number } {
  const [year, month, day] = date.split('-').map(Number)
  return { day: day ?? 1, month: (month ?? 1) - 1, year: year ?? 0 }
}

/** "8 e 9 out 2027", "8 a 10 out 2027", "30 set a 2 out 2027". */
export function formatEditionDates(startsOn: string | null, endsOn: string | null): string | null {
  if (!startsOn) return null

  const start = parse(startsOn)
  const end = parse(endsOn ?? startsOn)

  if (start.year === end.year && start.month === end.month) {
    if (start.day === end.day) return `${start.day} ${MONTHS[start.month]} ${start.year}`
    const separator = end.day - start.day === 1 ? 'e' : 'a'
    return `${start.day} ${separator} ${end.day} ${MONTHS[start.month]} ${start.year}`
  }

  const startYear = start.year === end.year ? '' : ` ${start.year}`
  return `${start.day} ${MONTHS[start.month]}${startYear} a ${end.day} ${MONTHS[end.month]} ${end.year}`
}

export function statusTone(status: string): BadgeTone {
  return ({ draft: 'info', cfp_open: 'warn', sales_open: 'ok', ongoing: 'ok', closed: 'neutral' } as Record<string, BadgeTone>)[status] ?? 'neutral'
}

export function slugify(text: string): string {
  return text
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 64)
}

export function initials(slug: string, name: string): string {
  const source = slug.split('-').pop() || name
  return source.slice(0, 2).toUpperCase()
}

export const BRAZILIAN_STATES = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO']
