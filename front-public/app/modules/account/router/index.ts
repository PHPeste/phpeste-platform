import type { RouteRecordRaw } from 'vue-router'

export const accountRoutes: RouteRecordRaw[] = [
  { path: '/conta', redirect: '/conta/ingressos' },
  { path: '/conta/ingressos', name: 'account-tickets', component: () => import('../views/MyTicketsView.vue') },
  { path: '/conta/ingressos/:id', name: 'account-ticket', component: () => import('../views/TicketView.vue') },
  { path: '/conta/pedidos', name: 'account-orders', component: () => import('../views/MyOrdersView.vue') },
]

export default accountRoutes
