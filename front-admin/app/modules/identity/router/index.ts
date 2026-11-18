import type { RouteRecordRaw } from 'vue-router'

const IdentityView = () => import('../views/IdentityView.vue')

export const identityRoutes: RouteRecordRaw[] = [
  { path: '/identidade', name: 'identity', component: IdentityView },
]

export default identityRoutes
