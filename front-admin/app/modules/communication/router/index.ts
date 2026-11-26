import type { RouteRecordRaw } from 'vue-router'

const CommunicationView = () => import('../views/CommunicationView.vue')

export const communicationRoutes: RouteRecordRaw[] = [
  { path: '/comunicacao', name: 'communication', component: CommunicationView },
]

export default communicationRoutes
