import type { RouteRecordRaw } from 'vue-router'

const TasksView = () => import('../views/TasksView.vue')

export const tasksRoutes: RouteRecordRaw[] = [
  { path: '/tarefas', name: 'tasks', component: TasksView },
]

export default tasksRoutes
