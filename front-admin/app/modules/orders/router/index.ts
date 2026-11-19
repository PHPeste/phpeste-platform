import type { RouteRecordRaw } from 'vue-router'

const OrdersView = () => import('../views/OrdersView.vue')

export const ordersRoutes: RouteRecordRaw[] = [
  { path: '/pedidos', name: 'orders', component: OrdersView },
]

export default ordersRoutes
