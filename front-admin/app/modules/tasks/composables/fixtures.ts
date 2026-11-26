export const taskPayload = (id: string, columnId: string, title: string, extra: Record<string, unknown> = {}) => ({
  id, column_id: columnId, position: 0, title, description: null, assignee: { id: 'u-ana', name: 'Ana Lima' }, due_on: '2027-08-15', overdue: false, done: false, link: null,
  updated_at: '2027-03-01T12:00:00Z', ...extra,
})

export const boardPayload = (canManage = true) => ({
  data: {
    filter: 'all', can_manage: canManage,
    columns: [
      { id: 'c-todo', name: 'A fazer', position: 0, is_done: false, count: 2, tasks: [taskPayload('t-1', 'c-todo', 'Fechar contrato do buffet'), taskPayload('t-2', 'c-todo', 'Pedir arte dos crachás', { assignee: { id: 'u-bia', name: 'Bia' } })] },
      { id: 'c-doing', name: 'Em andamento', position: 1, is_done: false, count: 1, tasks: [taskPayload('t-3', 'c-doing', 'Convidar palestrantes', { overdue: true, due_on: '2027-03-01' })] },
      { id: 'c-review', name: 'Em revisão', position: 2, is_done: false, count: 0, tasks: [] },
      { id: 'c-done', name: 'Feito', position: 3, is_done: true, count: 0, tasks: [] },
    ],
    totals: { tasks: 3, overdue: 1 },
  },
})
