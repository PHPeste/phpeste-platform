import { ref } from 'vue'
import overviewService, { type OverviewPayload } from '../services/overviewService'
import locale from '../locales/pt-br'
import type { EditionOverview, Indicator, Tone } from '../types'

const MONTHS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro']
const SHORT_MONTHS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']
const TONES: Tone[] = ['ok', 'warn', 'danger', 'info', 'neutral']

const fill = (text: string, values: Record<string, string | number>) => text.replace(/\{(\w+)\}/g, (_, key: string) => String(values[key] ?? ''))
const integer = (value: number) => new Intl.NumberFormat('pt-BR').format(value)
const money = (cents: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(cents / 100)

function parts(date: string) {
  const [year, month, day] = date.split('-').map(Number)
  return { year: year ?? 0, month: (month ?? 1) - 1, day: day ?? 1 }
}

export function formatShortDate(date: string): string {
  const { year, month, day } = parts(date)
  return `${day} ${SHORT_MONTHS[month]} ${year}`
}

export function formatLongRange(startsOn: string | null, endsOn: string | null): string | null {
  if (!startsOn) return null
  const start = parts(startsOn)
  const end = parts(endsOn ?? startsOn)
  if (start.year === end.year && start.month === end.month) {
    const days = start.day === end.day ? `${start.day}` : `${start.day} ${end.day - start.day === 1 ? 'e' : 'a'} ${end.day}`
    return `${days} de ${MONTHS[start.month]} de ${start.year}`
  }
  return `${start.day} de ${MONTHS[start.month]} a ${end.day} de ${MONTHS[end.month]} de ${end.year}`
}

function indicators(payload: OverviewPayload['data']['indicators']): Indicator[] {
  const { tickets, revenue, proposals, speakers } = payload

  return [
    {
      label: locale.tickets,
      value: tickets ? integer(tickets.sold) : '—',
      hint: tickets ? fill(locale.ticketsHint, { sold: integer(tickets.sold), available: integer(tickets.available) }) : locale.noSalesYet,
    },
    {
      label: locale.revenue,
      value: revenue ? money(revenue.gross) : '—',
      hint: revenue ? fill(locale.revenueHint, { net: money(revenue.net) }) : locale.noSalesYet,
    },
    {
      label: locale.proposals,
      value: integer(proposals?.total ?? 0),
      hint: proposals && proposals.total > 0 ? fill(locale.proposalsHint, { n: integer(proposals.reviewed) }) : locale.noCfpYet,
    },
    {
      label: locale.speakers,
      value: integer(speakers?.confirmed ?? 0),
      hint: fill(locale.speakersHint, { n: integer(speakers?.goal ?? 0) }),
    },
  ]
}

export function translate(payload: OverviewPayload['data']): EditionOverview {
  const { edition, stage, sections } = payload
  const place = [edition.city, edition.state ? `(${edition.state})` : null].filter(Boolean).join(' ')
  const weeks = sections.tickets_per_week

  return {
    subtitle: [edition.name, formatLongRange(edition.starts_on, edition.ends_on), place].filter(Boolean).join(' · '),
    daysUntilEvent: stage.days_until_event,
    steps: stage.steps,
    indicators: indicators(payload.indicators),
    ticketsPerWeek: weeks === null ? null : weeks.map((value, index) => ({ label: `sem ${index + 1}`, value })),
    pending: payload.pending.map((item) => ({
      key: item.key,
      title: item.title,
      tone: TONES.find((tone) => tone === item.tone) ?? 'neutral',
      tag: item.tag,
      actionLabel: item.action_label,
      actionPath: item.action_path,
    })),
    nordeste: sections.nordeste === null ? null : {
      base: sections.nordeste.base,
      people: sections.nordeste.people,
      percent: sections.nordeste.percent,
      quota: sections.nordeste.quota,
      requireQuota: sections.nordeste.require_quota,
      missingStates: sections.nordeste.missing_states.map((state) => state.name),
    },
    milestones: payload.milestones,
  }
}

export function useOverview() {
  const overview = ref<EditionOverview | null>(null)
  const state = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')

  async function load(editionId: string): Promise<void> {
    state.value = 'loading'

    try {
      overview.value = translate((await overviewService.get(editionId)).data)
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  return { overview, state, load }
}

export { fill as fillText }
