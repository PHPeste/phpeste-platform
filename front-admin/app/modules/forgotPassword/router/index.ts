import type { RouteRecordRaw } from 'vue-router'

const ForgotPasswordView = () => import('../views/ForgotPasswordView.vue')

export const forgotPasswordRoutes: RouteRecordRaw[] = [
  { path: '/esqueci-senha', name: 'forgot-password', component: ForgotPasswordView },
]

export default forgotPasswordRoutes
