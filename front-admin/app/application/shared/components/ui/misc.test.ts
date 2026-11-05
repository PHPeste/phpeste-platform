import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Card from './Card.vue'
import EmptyState from './EmptyState.vue'
import Kpi from './Kpi.vue'
import Skeleton from './Skeleton.vue'
import Stripe from './Stripe.vue'
import Table from './Table.vue'

describe('PhCard', () => {
  it('mostra título, conteúdo e ações', async () => {
    const wrapper = await mountSuspended(Card, {
      props: { title: 'Regras' },
      slots: { default: '<p>corpo</p>', actions: '<button>Editar</button>' },
    })
    expect(wrapper.find('h2').text()).toBe('Regras')
    expect(wrapper.text()).toContain('corpo')
    expect(wrapper.text()).toContain('Editar')
  })

  it('sem título e sem ações não cria cabeçalho', async () => {
    const wrapper = await mountSuspended(Card, { slots: { default: '<p>corpo</p>' } })
    expect(wrapper.find('header').exists()).toBe(false)
  })
})

describe('PhKpi', () => {
  it('relaciona rótulo e valor como lista de definição', async () => {
    const wrapper = await mountSuspended(Kpi, { props: { label: 'Ingressos', value: 128, hint: 'de 400' } })
    expect(wrapper.find('dt').text()).toBe('Ingressos')
    expect(wrapper.findAll('dd').map((d) => d.text())).toEqual(['128', 'de 400'])
  })
})

describe('PhTable', () => {
  it('tem legenda acessível e região rolável focável', async () => {
    const wrapper = await mountSuspended(Table, {
      props: { caption: 'Pedidos recentes' },
      slots: { default: '<tbody><tr><td>#1</td></tr></tbody>' },
    })
    expect(wrapper.find('caption').text()).toBe('Pedidos recentes')
    const region = wrapper.find('[role="region"]')
    expect(region.attributes('aria-label')).toBe('Pedidos recentes')
    expect(region.attributes('tabindex')).toBe('0')
  })
})

describe('PhSkeleton', () => {
  it('anuncia "Carregando" e marca ocupado, sem expor as barras', async () => {
    const wrapper = await mountSuspended(Skeleton, { props: { lines: 4 } })
    expect(wrapper.attributes('aria-busy')).toBe('true')
    expect(wrapper.text()).toContain('Carregando')
    expect(wrapper.findAll('.skeleton')).toHaveLength(4)
  })
})

describe('PhEmptyState', () => {
  it('mostra título, descrição e a próxima ação', async () => {
    const wrapper = await mountSuspended(EmptyState, {
      props: { title: 'Nenhuma proposta ainda', description: 'Quando chegar a primeira, aparece aqui.' },
      slots: { default: '<button>Ver formulário</button>' },
    })
    expect(wrapper.find('h3').text()).toBe('Nenhuma proposta ainda')
    expect(wrapper.text()).toContain('Quando chegar a primeira')
    expect(wrapper.text()).toContain('Ver formulário')
  })
})

describe('PhStripe', () => {
  it('é decorativa e fica escondida de leitores de tela', async () => {
    const wrapper = await mountSuspended(Stripe)
    expect(wrapper.attributes('aria-hidden')).toBe('true')
  })
})
