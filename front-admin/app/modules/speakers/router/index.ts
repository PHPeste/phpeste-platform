import type { RouteRecordRaw } from 'vue-router'

const SpeakersView = () => import('../views/SpeakersView.vue')
const SpeakerDetailView = () => import('../views/SpeakerDetailView.vue')

export const speakersRoutes: RouteRecordRaw[] = [
  { path: '/palestrantes', name: 'speakers', component: SpeakersView },
  { path: '/palestrantes/:id', name: 'speaker', component: SpeakerDetailView },
]

export default speakersRoutes
