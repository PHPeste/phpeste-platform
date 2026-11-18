import type { RouteRecordRaw } from 'vue-router'

const CfpReviewView = () => import('../views/CfpReviewView.vue')

export const cfpReviewRoutes: RouteRecordRaw[] = [
  { path: '/cfp/avaliacao', name: 'review', component: CfpReviewView },
]

export default cfpReviewRoutes
