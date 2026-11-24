import type { RouteRecordRaw } from 'vue-router'

const FinanceView = () => import('../views/FinanceView.vue')

export const financeRoutes: RouteRecordRaw[] = [
  { path: '/financas', name: 'finance', component: FinanceView },
]

export default financeRoutes
