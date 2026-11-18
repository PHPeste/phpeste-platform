import type { RouteRecordRaw } from 'vue-router'

const CommunitiesView = () => import('../views/CommunitiesView.vue')

export const communitiesRoutes: RouteRecordRaw[] = [
  { path: '/comunidades', name: 'communities', component: CommunitiesView },
]

export default communitiesRoutes
