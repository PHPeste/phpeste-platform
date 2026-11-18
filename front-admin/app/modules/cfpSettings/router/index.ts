import type { RouteRecordRaw } from 'vue-router'

const CfpSettingsView = () => import('../views/CfpSettingsView.vue')

export const cfpSettingsRoutes: RouteRecordRaw[] = [
  { path: '/cfp', name: 'cfp', component: CfpSettingsView },
]

export default cfpSettingsRoutes
