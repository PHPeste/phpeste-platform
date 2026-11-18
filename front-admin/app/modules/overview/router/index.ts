import type { RouteRecordRaw } from 'vue-router'

const OverviewView = () => import('../views/OverviewView.vue')

export const overviewRoutes: RouteRecordRaw[] = [
  { path: '/', name: 'overview', component: OverviewView },
]

export default overviewRoutes
