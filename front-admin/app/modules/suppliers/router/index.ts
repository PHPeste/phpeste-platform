import type { RouteRecordRaw } from 'vue-router'

const SuppliersView = () => import('../views/SuppliersView.vue')
const SupplierDetailView = () => import('../views/SupplierDetailView.vue')

export const suppliersRoutes: RouteRecordRaw[] = [
  { path: '/fornecedores', name: 'suppliers', component: SuppliersView },
  { path: '/fornecedores/:id', name: 'supplier', component: SupplierDetailView },
]

export default suppliersRoutes
