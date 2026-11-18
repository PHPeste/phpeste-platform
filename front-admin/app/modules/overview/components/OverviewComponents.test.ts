import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import NordesteGoalCard from './NordesteGoalCard.vue'
import PendingList from './PendingList.vue'
import StageTimeline from './StageTimeline.vue'
import TicketsChart from './TicketsChart.vue'

describe('StageTimeline', () => {
  it('destaca a etapa atual e conta os dias para o evento', async () => {
    const wrapper = await mountSuspended(StageTimeline, {
      props: {
        daysUntilEvent: 339,
        steps: [
          { status: 'draft', label: 'Rascunho', note: 'Preparação', state: 'done' },
          { status: 'cfp_open', label: 'CFP aberto', note: 'Chamada', state: 'current' },
          { status: 'sales_open', label: 'Vendas abertas', note: 'Venda', state: 'next' },
          { status: 'ongoing', label: 'Em andamento', note: 'Evento', state: 'upcoming' },
        ],
      },
    })

    expect(wrapper.text()).toContain('Faltam 339 dias para o evento')
    expect(wrapper.find('[aria-current="step"]').text()).toContain('CFP aberto')
    expect(wrapper.text()).toContain('Concluída')
    expect(wrapper.text()).toContain('Próxima')
    expect(wrapper.text()).toContain('Evento')
  })
})

describe('TicketsChart', () => {
  const weeks = [12, 18, 25, 31, 22, 40, 55, 48].map((value, index) => ({ label: `sem ${index + 1}`, value }))

  it('tem resumo em texto e uma barra focável por semana', async () => {
    const wrapper = await mountSuspended(TicketsChart, { props: { weeks } })

    expect(wrapper.find('svg[role="img"]').exists()).toBe(true)
    expect(wrapper.find('figcaption').text()).toBe('Ingressos vendidos por semana nas últimas 8 semanas: mínimo de 12, máximo de 55 na sem 7, total de 251.')
    expect(wrapper.findAll('rect[tabindex="0"]')).toHaveLength(8)
    expect(wrapper.find('rect[aria-label="sem 7: 55 ingressos"]').exists()).toBe(true)
  })

  it('mostra o valor ao focar a barra e troca para a tabela equivalente', async () => {
    const wrapper = await mountSuspended(TicketsChart, { props: { weeks } })

    await wrapper.findAll('rect[tabindex="0"]')[2]!.trigger('focus')
    expect(wrapper.find('[role="status"]').text()).toBe('25 · sem 3')

    await wrapper.find('button[aria-pressed]').trigger('click')
    expect(wrapper.findAll('tbody tr')).toHaveLength(8)
    expect(wrapper.find('tbody tr').text()).toContain('sem 1')
  })

  it('explica quando ainda não há vendas', async () => {
    const wrapper = await mountSuspended(TicketsChart, { props: { weeks: null } })

    expect(wrapper.text()).toContain('O gráfico aparece quando as vendas começarem.')
    expect(wrapper.find('svg').exists()).toBe(false)
  })
})

describe('PendingList', () => {
  it('leva cada pendência à tela que resolve, com o filtro', async () => {
    const wrapper = await mountSuspended(PendingList, {
      props: { items: [{ key: 'x', title: '7 propostas sem revisor', tone: 'warn', tag: 'Atenção', actionLabel: 'Atribuir', actionPath: '/cfp/avaliacao?filtro=sem-revisor' }] },
    })

    expect(wrapper.find('a[href="/cfp/avaliacao?filtro=sem-revisor"]').text()).toContain('Atribuir')
  })

  it('diz quando não há nada pendente', async () => {
    const wrapper = await mountSuspended(PendingList, { props: { items: [] } })

    expect(wrapper.text()).toContain('Nada pendente por aqui.')
  })
})

describe('NordesteGoalCard', () => {
  it('mostra o percentual, a meta e os estados sem representante', async () => {
    const wrapper = await mountSuspended(NordesteGoalCard, {
      props: { goal: { base: 'confirmed', people: 8, percent: 38, quota: 50, requireQuota: true, missingStates: ['Alagoas', 'Sergipe'] } },
    })

    expect(wrapper.text()).toContain('38% dos confirmados são do Nordeste')
    expect(wrapper.text()).toContain('abaixo da meta')
    expect(wrapper.text()).toContain('Alagoas')
    expect(wrapper.find('progress').attributes('value')).toBe('38')
  })
})
