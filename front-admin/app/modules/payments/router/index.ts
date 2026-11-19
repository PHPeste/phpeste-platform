import type { RouteRecordRaw } from 'vue-router'

const PaymentsView = () => import('../views/PaymentsView.vue')

export const paymentsRoutes: RouteRecordRaw[] = [
  { path: '/pagamentos', name: 'payments', component: PaymentsView },
]

export default paymentsRoutes
