import type { RouteRecordRaw } from 'vue-router'

const ResetPasswordView = () => import('../views/ResetPasswordView.vue')

export const resetPasswordRoutes: RouteRecordRaw[] = [
  { path: '/redefinir-senha', name: 'reset-password', component: ResetPasswordView },
]

export default resetPasswordRoutes
