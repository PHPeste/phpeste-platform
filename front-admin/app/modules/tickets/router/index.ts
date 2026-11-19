import type { RouteRecordRaw } from 'vue-router'

const TicketsView = () => import('../views/TicketsView.vue')

export const ticketsRoutes: RouteRecordRaw[] = [
  { path: '/ingressos', name: 'tickets', component: TicketsView },
]

export default ticketsRoutes
