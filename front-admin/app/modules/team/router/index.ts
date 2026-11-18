import type { RouteRecordRaw } from 'vue-router'

const TeamView = () => import('../views/TeamView.vue')

export const teamRoutes: RouteRecordRaw[] = [
  { path: '/equipe', name: 'team', component: TeamView },
]

export default teamRoutes
