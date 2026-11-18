import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it } from 'vitest'
import { useEdition } from '@/application/shared/composables/useEdition'
import EditionReadOnlyBanner from './EditionReadOnlyBanner.vue'

beforeEach(() => useEdition().clear())

describe('EditionReadOnlyBanner', () => {
  it('avisa que a edição encerrada está em modo leitura', async () => {
    useEdition().setCurrent({ id: 'ed-26', slug: '2026', name: 'PHPeste 2026', status: 'closed' })
    const wrapper = await mountSuspended(EditionReadOnlyBanner)

    expect(wrapper.find('[role="status"]').text()).toContain('Edição encerrada.')
  })

  it('não aparece em edição em andamento', async () => {
    useEdition().setCurrent({ id: 'ed-27', slug: '2027', name: 'PHPeste 2027', status: 'cfp_open' })
    const wrapper = await mountSuspended(EditionReadOnlyBanner)

    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })
})
