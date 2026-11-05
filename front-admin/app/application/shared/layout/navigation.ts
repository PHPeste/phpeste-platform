import {
  CalendarDays, ClipboardCheck, Columns3, CreditCard, FileText, House, Mail, Mic, Palette,
  Receipt, ScanLine, SlidersHorizontal, Star, Ticket, Truck, Users, Wallet,
} from '@lucide/vue'
import type { Component } from 'vue'
import type { EditionRole } from '@/modules/shell/types'

export type NavGroup = 'edition' | 'content' | 'sales' | 'organization' | 'system'

export type NavKey =
  | 'overview' | 'identity' | 'team' | 'cfp' | 'review' | 'speakers' | 'schedule'
  | 'tickets' | 'orders' | 'payments' | 'checkin' | 'sponsors' | 'suppliers'
  | 'finance' | 'tasks' | 'comms' | 'settings'

export interface NavItem {
  key: NavKey
  to: string
  group: NavGroup
  icon: Component
  /** Papéis que enxergam o item. O organizador vê tudo. */
  roles: EditionRole[]
}

const ALL: EditionRole[] = ['organizador', 'financeiro', 'avaliador', 'checkin', 'voluntario']

// Ordem e agrupamento iguais ao componente Sidebar do design.
export const NAV_ITEMS: NavItem[] = [
  { key: 'overview', to: '/', group: 'edition', icon: House, roles: ALL },
  { key: 'identity', to: '/identidade', group: 'edition', icon: Palette, roles: ['organizador'] },
  { key: 'team', to: '/equipe', group: 'edition', icon: Users, roles: ['organizador'] },
  { key: 'cfp', to: '/cfp', group: 'content', icon: FileText, roles: ['organizador'] },
  { key: 'review', to: '/cfp/avaliacao', group: 'content', icon: ClipboardCheck, roles: ['organizador', 'avaliador'] },
  { key: 'speakers', to: '/palestrantes', group: 'content', icon: Mic, roles: ['organizador'] },
  { key: 'schedule', to: '/grade', group: 'content', icon: CalendarDays, roles: ['organizador'] },
  { key: 'tickets', to: '/ingressos', group: 'sales', icon: Ticket, roles: ['organizador', 'financeiro'] },
  { key: 'orders', to: '/pedidos', group: 'sales', icon: Receipt, roles: ['organizador', 'financeiro'] },
  { key: 'payments', to: '/pagamentos', group: 'sales', icon: CreditCard, roles: ['organizador', 'financeiro'] },
  { key: 'checkin', to: '/participantes', group: 'sales', icon: ScanLine, roles: ['organizador', 'checkin', 'voluntario'] },
  { key: 'sponsors', to: '/patrocinadores', group: 'organization', icon: Star, roles: ['organizador'] },
  { key: 'suppliers', to: '/fornecedores', group: 'organization', icon: Truck, roles: ['organizador'] },
  { key: 'finance', to: '/financas', group: 'organization', icon: Wallet, roles: ['organizador', 'financeiro'] },
  { key: 'tasks', to: '/tarefas', group: 'organization', icon: Columns3, roles: ['organizador'] },
  { key: 'comms', to: '/comunicacao', group: 'system', icon: Mail, roles: ['organizador'] },
  { key: 'settings', to: '/configuracoes', group: 'system', icon: SlidersHorizontal, roles: ['organizador'] },
]

export const NAV_GROUPS: NavGroup[] = ['edition', 'content', 'sales', 'organization', 'system']

export function canSee(item: NavItem, role: EditionRole | null): boolean {
  return role !== null && item.roles.includes(role)
}

/** Item cuja rota é o prefixo mais específico de `path` (ou null). */
export function findNavItem(path: string): NavItem | null {
  const clean = path.replace(/\/+$/, '') || '/'
  let best: NavItem | null = null
  for (const item of NAV_ITEMS) {
    const match = item.to === '/' ? clean === '/' : clean === item.to || clean.startsWith(`${item.to}/`)
    if (match && (!best || item.to.length > best.to.length)) best = item
  }
  return best
}
