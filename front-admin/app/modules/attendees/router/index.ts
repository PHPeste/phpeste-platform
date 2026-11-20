import type { RouteRecordRaw } from 'vue-router'

const AttendeesView = () => import('../views/AttendeesView.vue')

export const attendeesRoutes: RouteRecordRaw[] = [
  { path: '/participantes', name: 'attendees', component: AttendeesView },
]

export default attendeesRoutes
