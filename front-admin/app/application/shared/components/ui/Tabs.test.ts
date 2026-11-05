import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Tabs from './Tabs.vue'

const items = [
  { id: 'geral', label: 'Geral' },
  { id: 'trilhas', label: 'Trilhas' },
  { id: 'criterios', label: 'Critérios' },
]

async function mountTabs(active = 'geral') {
  const wrapper = await mountSuspended(Tabs, {
    props: { items, label: 'Seções', modelValue: active, 'onUpdate:modelValue': (v: string) => wrapper.setProps({ modelValue: v }) },
    slots: { default: '<p>conteúdo</p>' },
  })
  return wrapper
}

describe('PhTabs', () => {
  it('usa os papéis tablist, tab e tabpanel e liga painel e aba', async () => {
    const wrapper = await mountTabs()
    expect(wrapper.find('[role="tablist"]').attributes('aria-label')).toBe('Seções')
    const tabs = wrapper.findAll('[role="tab"]')
    expect(tabs).toHaveLength(3)
    expect(tabs[0]!.attributes('aria-selected')).toBe('true')
    const panel = wrapper.find('[role="tabpanel"]')
    expect(panel.attributes('aria-labelledby')).toBe(tabs[0]!.attributes('id'))
    expect(tabs[0]!.attributes('aria-controls')).toBe(panel.attributes('id'))
  })

  it('só a aba ativa entra na ordem de Tab (roving tabindex)', async () => {
    const wrapper = await mountTabs()
    const tabindexes = wrapper.findAll('[role="tab"]').map((t) => t.attributes('tabindex'))
    expect(tabindexes).toEqual(['0', '-1', '-1'])
  })

  it('setas, Home e End trocam de aba', async () => {
    const wrapper = await mountTabs()
    const list = wrapper.find('[role="tablist"]')
    await list.trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.props('modelValue')).toBe('trilhas')
    await list.trigger('keydown', { key: 'End' })
    expect(wrapper.props('modelValue')).toBe('criterios')
    await list.trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.props('modelValue')).toBe('geral')
    await list.trigger('keydown', { key: 'ArrowLeft' })
    expect(wrapper.props('modelValue')).toBe('criterios')
    await list.trigger('keydown', { key: 'Home' })
    expect(wrapper.props('modelValue')).toBe('geral')
  })

  it('clicar numa aba a ativa', async () => {
    const wrapper = await mountTabs()
    await wrapper.findAll('[role="tab"]')[1]!.trigger('click')
    expect(wrapper.props('modelValue')).toBe('trilhas')
  })
})
