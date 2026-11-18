import type { RouteRecordRaw } from 'vue-router'

const AcceptInvitationView = () => import('../views/AcceptInvitationView.vue')

export const invitationRoutes: RouteRecordRaw[] = [
  { path: '/convite/:token', name: 'invitation', component: AcceptInvitationView },
]

export default invitationRoutes
