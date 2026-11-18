import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import CoverageGrid from './CoverageGrid.vue'
import LogisticsTab from './LogisticsTab.vue'

const logistics = { originCity: 'Recife', originState: 'PE', needsTravel: true, needsLodging: false, travelPaidByOrganization: false, lodgingPaidByOrganization: false, organizationNotes: '', accessibilityNeeds: 'Rampa no palco', dietaryRestrictions: null }

describe('CoverageGrid', () => {
  it('destaca os estados sem representante e diz quais faltam', async () => {
    const wrapper = await mountSuspended(CoverageGrid, { props: { coverage: [{ state: 'AL', confirmed: 0 }, { state: 'PB', confirmed: 3 }], missing: ['AL'] } })

    expect(wrapper.find('[data-state="AL"]').text()).toContain('sem representante')
    expect(wrapper.find('[data-state="PB"]').text()).toContain('3confirmados')
    expect(wrapper.text()).toContain('1 estado sem representante: AL.')
  })
})

describe('LogisticsTab', () => {
  const save = async () => ({ ok: true })

  it('mostra acessibilidade e alimentação só com permissão', async () => {
    const allowed = await mountSuspended(LogisticsTab, { props: { logistics, canViewPrivateData: true, disabled: false, save } })
    expect(allowed.find('[data-testid="private-data"]').text()).toContain('Rampa no palco')

    const denied = await mountSuspended(LogisticsTab, { props: { logistics: { ...logistics, accessibilityNeeds: null }, canViewPrivateData: false, disabled: false, save } })
    expect(denied.find('[data-testid="private-data"]').exists()).toBe(false)
    expect(denied.text()).not.toContain('acessibilidade')
  })
})
