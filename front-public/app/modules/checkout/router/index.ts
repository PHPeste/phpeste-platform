import type { RouteRecordRaw } from 'vue-router'

const CheckoutView = () => import('../views/CheckoutView.vue')
const OrderView = () => import('../views/OrderView.vue')

export const checkoutRoutes: RouteRecordRaw[] = [
  { path: '/ingressos', name: 'checkout', component: CheckoutView },
  { path: '/checkout/pedido/:id', name: 'checkout-order', component: OrderView },
]

export default checkoutRoutes
