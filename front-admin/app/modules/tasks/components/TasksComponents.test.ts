import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import TaskCard from './TaskCard.vue'
import type { Task } from '../types'

const task = (extra: Partial<Task> = {}): Task => ({
  id: 't-1', columnId: 'c-1', title: 'Convidar palestrantes de AL e SE', description: null, assigneeId: 'u-1', assigneeName: 'João Pedro', dueOn: '2020-01-01', overdue: true, done: false, link: null, ...extra,
})

describe('TaskCard', () => {
  it('atrasada ganha destaque e texto, com o responsável lido pelo leitor de tela', async () => {
    const wrapper = await mountSuspended(TaskCard, { props: { task: task(), columnName: 'Em andamento', draggable: true, editable: false, dragging: false } })

    expect(wrapper.text()).toContain('Atrasada')
    expect(wrapper.text()).toMatch(/atrasada \d+ dias/)
    expect(wrapper.text()).toContain('Responsável: João Pedro')
    expect(wrapper.attributes('aria-label')).toBe('Convidar palestrantes de AL e SE. Em andamento.')
    expect(wrapper.attributes('aria-describedby')).toBe('tasks-keyboard-hint')
  })

  it('atrasada sem dias para contar não mostra "0 dias"', async () => {
    const today = new Date()
    const date = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
    const wrapper = await mountSuspended(TaskCard, { props: { task: task({ dueOn: date }), columnName: 'Fazendo', draggable: true, editable: false, dragging: false } })

    expect(wrapper.text()).not.toContain('0 dias')
    expect(wrapper.text()).toContain('atrasada')
  })

  it('concluída mostra "concluída" e nada de atraso', async () => {
    const wrapper = await mountSuspended(TaskCard, { props: { task: task({ overdue: false, done: true }), columnName: 'Feito', draggable: false, editable: true, dragging: false } })

    expect(wrapper.text()).toContain('concluída')
    expect(wrapper.text()).not.toContain('Atrasada')
    expect(wrapper.attributes('draggable')).toBe('false')
    expect(wrapper.find('button[aria-label="Editar Convidar palestrantes de AL e SE"]').exists()).toBe(true)
  })
})
