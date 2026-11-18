import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import ColorField from './ColorField.vue'
import ContrastReport from './ContrastReport.vue'
import ThemePreview from './ThemePreview.vue'

describe('ColorField', () => {
  it('aceita hexadecimal digitado e avisa quando o formato está errado', async () => {
    const wrapper = await mountSuspended(ColorField, { props: { label: 'Marca', modelValue: '#6B2A12' } })

    await wrapper.find('input:not([type="color"])').setValue('C9B48A')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['#C9B48A'])

    await wrapper.setProps({ modelValue: '#C9B4' })
    expect(wrapper.find('[role="alert"]').text()).toBe('Use o formato #RRGGBB.')
    expect(wrapper.find('input[type="color"]').attributes('aria-label')).toBe('Escolher Marca')
  })
})

describe('ContrastReport', () => {
  it('põe os pares que falham primeiro e diz quantos são', async () => {
    const wrapper = await mountSuspended(ContrastReport, {
      props: {
        results: [
          { foreground: 'ink', background: 'bg', minimum: 4.5, label: 'Texto sobre Fundo', ratio: 14.77, level: 'AAA', passes: true },
          { foreground: 'on_brand', background: 'brand', minimum: 4.5, label: 'Texto sobre marca sobre Marca', ratio: 3.1, level: 'fail', passes: false },
        ],
      },
    })

    expect(wrapper.find('[role="status"]').text()).toBe('1 par abaixo do mínimo. Ajuste antes de publicar.')
    expect(wrapper.findAll('li')[0]!.text()).toContain('3,10:1')
    expect(wrapper.findAll('li')[0]!.text()).toContain('Abaixo do mínimo (mínimo 4,50:1)')
    expect(wrapper.findAll('li')[1]!.text()).toContain('AAA')
  })
})

describe('ThemePreview', () => {
  it('aplica os tokens em edição no site de exemplo', async () => {
    const wrapper = await mountSuspended(ThemePreview, {
      props: { colors: { bg: '#0F1B2D', brand: '#2C78CA', ink: '#F2F6FA', on_brand: '#FFFFFF' }, font: 'Inter', radius: 6, logo: null, editionName: 'PHPeste 2027', dates: '' },
    })

    const style = wrapper.find('[data-testid="theme-preview"]').attributes('style')
    expect(style).toContain('--p-bg: #0F1B2D')
    expect(style).toContain('--p-brand: #2C78CA')
    expect(style).toContain('--p-radius: 6px')
    expect(style).toContain('Inter')
  })
})
