import type { RouteRecordRaw } from 'vue-router'

const EditionSettingsView = () => import('../views/EditionSettingsView.vue')

export const editionSettingsRoutes: RouteRecordRaw[] = [
  { path: '/configuracoes', name: 'settings', component: EditionSettingsView },
]

export default editionSettingsRoutes
