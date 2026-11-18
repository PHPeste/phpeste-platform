import { httpClient } from '@/infrastructure/http/HttpClient'

export interface OverviewPayload {
  data: {
    edition: { id: string, name: string, starts_on: string | null, ends_on: string | null, city: string | null, state: string | null }
    stage: { current: string, days_until_event: number | null, steps: Array<{ status: string, label: string, note: string, state: 'done' | 'current' | 'next' | 'upcoming' }> }
    indicators: {
      tickets: { sold: number, available: number } | null
      revenue: { gross: number, net: number } | null
      proposals: { total: number, reviewed: number } | null
      speakers: { confirmed: number, goal: number } | null
    }
    sections: {
      tickets_per_week: number[] | null
      nordeste: { base: 'confirmed' | 'accepted', people: number, percent: number | null, quota: number, require_quota: boolean, missing_states: Array<{ uf: string, name: string }> } | null
    }
    pending: Array<{ key: string, title: string, tone: string, tag: string, action_label: string, action_path: string }>
    milestones: Array<{ key: string, title: string, description: string, date: string }>
  }
}

export default {
  async get(editionId: string): Promise<OverviewPayload> {
    return httpClient.get<OverviewPayload>(`/editions/${editionId}/overview`)
  },
}
