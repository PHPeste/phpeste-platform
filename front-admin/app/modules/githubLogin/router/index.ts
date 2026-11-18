import type { RouteRecordRaw } from 'vue-router'

const GithubCallbackView = () => import('../views/GithubCallbackView.vue')

export const githubLoginRoutes: RouteRecordRaw[] = [
  { path: '/login/github', name: 'login-github', component: GithubCallbackView },
]

export default githubLoginRoutes
