import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Pagination from './Pagination.vue'

describe('PhPagination', () => {
  it('marca a página atual com aria-current', async () => {
    const wrapper = await mountSuspended(Pagination, { props: { page: 2, pageCount: 9 } })
    expect(wrapper.find('[aria-current="page"]').text()).toBe('2')
    expect(wrapper.find('nav').attributes('aria-label')).toBe('Paginação')
  })

  it('emite a página escolhida e respeita os limites', async () => {
    const wrapper = await mountSuspended(Pagination, { props: { page: 1, pageCount: 3 } })
    const buttons = wrapper.findAll('button')
    await buttons[buttons.length - 1]!.trigger('click')
    expect(wrapper.emitted('update:page')?.at(-1)).toEqual([2])
  })

  it('desabilita "anterior" na primeira página e "próxima" na última', async () => {
    const first = await mountSuspended(Pagination, { props: { page: 1, pageCount: 3 } })
    expect(first.findAll('button')[0]!.attributes('disabled')).toBeDefined()
    const last = await mountSuspended(Pagination, { props: { page: 3, pageCount: 3 } })
    const buttons = last.findAll('button')
    expect(buttons[buttons.length - 1]!.attributes('disabled')).toBeDefined()
  })
})
