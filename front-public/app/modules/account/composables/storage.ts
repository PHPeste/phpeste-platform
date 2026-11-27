import { ACCOUNT_STORAGE_PREFIX } from '@/application/shared/accountStorage'
import type { AccountTicket } from '../types'

export interface SavedTickets { tickets: AccountTicket[], savedAt: string }

const key = (editionId: string, userId: string) => `${ACCOUNT_STORAGE_PREFIX}tickets.${editionId}.${userId}`

export function saveTickets(editionId: string, userId: string, tickets: AccountTicket[], now = new Date()) {
  try {
    localStorage.setItem(key(editionId, userId), JSON.stringify({ tickets, savedAt: now.toISOString() } satisfies SavedTickets))
  } catch {
    return
  }
}

export function savedTickets(editionId: string, userId: string): SavedTickets | null {
  try {
    const value = JSON.parse(localStorage.getItem(key(editionId, userId)) ?? 'null') as SavedTickets | null
    return value && Array.isArray(value.tickets) && typeof value.savedAt === 'string' ? value : null
  } catch {
    return null
  }
}

export function saveTicket(editionId: string, userId: string, ticket: AccountTicket) {
  const saved = savedTickets(editionId, userId)
  const tickets = saved?.tickets ?? []
  const index = tickets.findIndex((item) => item.id === ticket.id)
  saveTickets(editionId, userId, index === -1 ? [...tickets, ticket] : tickets.map((item) => (item.id === ticket.id ? ticket : item)))
}

export function forgetTicket(editionId: string, userId: string, ticketId: string) {
  const saved = savedTickets(editionId, userId)
  if (saved) saveTickets(editionId, userId, saved.tickets.filter((item) => item.id !== ticketId))
}
