import type { RouteRecordRaw } from 'vue-router'

const MyProfileView = () => import('../views/MyProfileView.vue')

export const myProfileRoutes: RouteRecordRaw[] = [
  { path: '/meu-perfil', name: 'my-profile', component: MyProfileView },
]

export default myProfileRoutes
