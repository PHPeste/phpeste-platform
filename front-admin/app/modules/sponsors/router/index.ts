import type { RouteRecordRaw } from 'vue-router'

const SponsorsView = () => import('../views/SponsorsView.vue')
const SponsorDetailView = () => import('../views/SponsorDetailView.vue')

export const sponsorsRoutes: RouteRecordRaw[] = [
  { path: '/patrocinadores', name: 'sponsors', component: SponsorsView },
  { path: '/patrocinadores/:id', name: 'sponsor', component: SponsorDetailView },
]

export default sponsorsRoutes
