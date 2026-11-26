import locale from '../locales/pt-br'

const PLACEHOLDER = /\{([a-z_]+)\}/g

/** Mesma regra do backend: "{nome}" vira variável, qualquer outra chave fica como texto. */
export const placeholders = (text: string) => [...new Set([...text.matchAll(PLACEHOLDER)].map((match) => match[1]!))]

export const unknownVariables = (text: string, known: string[]) => placeholders(text).filter((name) => !known.includes(name))

export const wrap = (name: string) => `{${name}}`

const time = new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' })
const dayMonth = new Intl.DateTimeFormat('pt-BR', { day: 'numeric', month: 'short' })
const dayMonthYear = new Intl.DateTimeFormat('pt-BR', { day: 'numeric', month: 'short', year: 'numeric' })

const sameDay = (a: Date, b: Date) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()

/** ISO → "hoje, 15:42", "ontem, 09:10", "12 de out., 15:42" ou "3 de mar. de 2026". */
export function formatLastSent(iso: string, now = new Date()): string {
  const date = new Date(iso)
  const yesterday = new Date(now)
  yesterday.setDate(now.getDate() - 1)
  if (sameDay(date, now)) return locale.today.replace('{hora}', time.format(date))
  if (sameDay(date, yesterday)) return locale.yesterday.replace('{hora}', time.format(date))
  if (date.getFullYear() === now.getFullYear()) return `${dayMonth.format(date)}, ${time.format(date)}`
  return dayMonthYear.format(date)
}
