import type { RouteRecordRaw } from 'vue-router'

export const proposalsRoutes: RouteRecordRaw[] = [
  { path: '/cfp', name: 'cfp', component: () => import('../views/ProposalWizardView.vue') },
  { path: '/palestrante/propostas', name: 'my-proposals', component: () => import('../views/MyProposalsView.vue') },
  { path: '/palestrante/propostas/:id', name: 'my-proposal', component: () => import('../views/MyProposalView.vue') },
]

export default proposalsRoutes
