import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import TemplateEditor from './TemplateEditor.vue'
import TemplateList from './TemplateList.vue'
import EmailPreview from './EmailPreview.vue'
import { translateTemplate } from '../composables/useEmailTemplates'
import { templatePayload } from '../composables/fixtures'

const template = translateTemplate(templatePayload('order_confirmed', 'Confirmação de compra'))
const errors = { subject: null, body: null }

describe('TemplateList', () => {
  it('mostra gatilho, último envio e "nunca", e marca o modelo aberto', async () => {
    const wrapper = await mountSuspended(TemplateList, {
      props: {
        templates: [template, translateTemplate(templatePayload('event_reminder', 'Antes do evento', { trigger: '7 dias antes', last_sent_at: '2026-11-20T11:00:00Z', customized: true }))],
        selectedId: 'order_confirmed',
      },
    })
    const rows = wrapper.findAll('tbody tr')
    expect(rows[0]!.text()).toContain('Ao pagar o pedido')
    expect(rows[0]!.text()).toContain('nunca')
    expect(rows[0]!.find('button').attributes('aria-current')).toBe('true')
    expect(rows[1]!.text()).toContain('Personalizado')
    expect(rows[1]!.text()).toMatch(/20 de nov/)

    await rows[1]!.find('button').trigger('click')
    expect(wrapper.emitted('select')).toEqual([['event_reminder']])
  })
})

describe('TemplateEditor', () => {
  it('insere a variável onde está o cursor do campo usado por último', async () => {
    const wrapper = await mountSuspended(TemplateEditor, {
      props: { template, draft: { subject: 'Seu ingresso', body: 'Oi!' }, errors, dirty: false, readOnly: false, busy: null },
      attachTo: document.body,
    })
    const subject = wrapper.find('#template-subject')
    await subject.trigger('focusin')
    ;(subject.element as HTMLInputElement).setSelectionRange(4, 4)
    await wrapper.find('button[aria-label="Inserir {edicao}"]').trigger('click')

    expect(wrapper.emitted('edit')?.at(-1)).toEqual(['subject', 'Seu {edicao}ingresso'])
    wrapper.unmount()
  })

  it('com variável inválida o salvar não sai e a lista de erros aparece', async () => {
    const wrapper = await mountSuspended(TemplateEditor, {
      props: { template, draft: { subject: 'Oi', body: 'Seu {ingresso}' }, errors: { subject: null, body: 'Variável desconhecida no modelo: {ingresso}.' }, dirty: true, readOnly: false, busy: null },
    })
    expect(wrapper.find('#template-body-error').text()).toContain('{ingresso}')

    await wrapper.find('form').trigger('submit')
    expect(wrapper.emitted('save')).toBeUndefined()
    expect(wrapper.find('[data-testid="form-errors"]').text()).toContain('Mensagem')
  })

  it('só leitura esconde as ações; restaurar só aparece em modelo personalizado', async () => {
    const readOnly = await mountSuspended(TemplateEditor, { props: { template, draft: { subject: 'a', body: 'b' }, errors, dirty: false, readOnly: true, busy: null } })
    expect(readOnly.text()).not.toContain('Salvar modelo')

    const editable = await mountSuspended(TemplateEditor, { props: { template, draft: { subject: 'a', body: 'b' }, errors, dirty: false, readOnly: false, busy: null } })
    expect(editable.text()).toContain('Enviar teste para mim')
    expect(editable.text()).not.toContain('Restaurar padrão')
    await editable.setProps({ template: { ...template, customized: true } })
    expect(editable.text()).toContain('Restaurar padrão')
  })
})

describe('EmailPreview', () => {
  it('mostra o HTML do backend num iframe isolado, com título', async () => {
    const wrapper = await mountSuspended(EmailPreview, { props: { label: 'Confirmação de compra', preview: { subject: 'Seu ingresso para o PHPeste 2027', html: '<p>oi</p>' }, state: 'ready' } })
    const frame = wrapper.find('iframe')
    expect(frame.attributes('sandbox')).toBe('')
    expect(frame.attributes('srcdoc')).toBe('<p>oi</p>')
    expect(frame.attributes('title')).toBe('Pré-visualização do e-mail "Confirmação de compra"')
    expect(wrapper.text()).toContain('Assunto: Seu ingresso para o PHPeste 2027')
  })

  it('bloqueada não mostra prévia velha', async () => {
    const wrapper = await mountSuspended(EmailPreview, { props: { label: 'x', preview: { subject: 's', html: '<p>oi</p>' }, state: 'blocked' } })
    expect(wrapper.find('iframe').exists()).toBe(false)
    expect(wrapper.text()).toContain('Corrija o assunto e a mensagem para ver a prévia.')
  })
})
