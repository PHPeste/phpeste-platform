import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import AttendeeDrawer from './AttendeeDrawer.vue'
import ReminderModal from './ReminderModal.vue'
import type { AttendeeDetail } from '../types'

const detail: AttendeeDetail = {
  attendee: {
    id: 't-1', code: 'K7QM-4XTD', active: true, statusLabel: 'Válido', orderNumber: 42, ticketTypeName: 'Geral', name: 'Carla Dias', email: 'carla@exemplo.com',
    badgeName: 'Carla', company: null, tshirtSize: 'M', accessibility: 'Intérprete de Libras', missing: [], transferredFrom: null, lastSentAt: null,
  },
  transfers: [],
  edits: [{ id: 'e-1', changes: [{ field: 'badge_name', label: 'Nome no crachá', from: null, to: 'Carla' }, { field: 'accessibility', label: 'Acessibilidade', from: null, to: null }], actorName: 'Rita', at: '2027-06-10T12:00:00Z' }],
}
const flat = (text: string) => text.replace(/\s+/g, ' ')
const tick = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('AttendeeDrawer', () => {
  it('mostra a acessibilidade como dado só da organização e salva o formulário', async () => {
    const save = vi.fn().mockResolvedValue({ ok: true })
    const wrapper = await mountSuspended(AttendeeDrawer, { props: { modelValue: true, detail, state: 'ready', disabled: false, canSeePrivate: true, save, resend: vi.fn() } })

    expect(flat(wrapper.find('[data-testid="attendee-private"]').text())).toContain('Visível só para a organização')
    expect(flat(wrapper.find('[data-testid="attendee-edits"]').text())).toContain('Nome no crachá: vazio → Carla')
    expect(flat(wrapper.find('[data-testid="attendee-edits"]').text())).toContain('Acessibilidade alterada')
    expect(flat(wrapper.find('[data-testid="attendee-edits"]').text())).not.toContain('Libras')

    await wrapper.find('#attendee-badge').setValue('Carlinha')
    await wrapper.find('#attendee-form').trigger('submit')
    expect(save).toHaveBeenCalledWith({ name: 'Carla Dias', badgeName: 'Carlinha', company: '', tshirtSize: 'M', accessibility: 'Intérprete de Libras' })
  })

  it('sem permissão, não mostra acessibilidade nem deixa editar', async () => {
    const wrapper = await mountSuspended(AttendeeDrawer, { props: { modelValue: true, detail, state: 'ready', disabled: true, canSeePrivate: false, save: vi.fn(), resend: vi.fn() } })

    expect(wrapper.find('[data-testid="attendee-private"]').exists()).toBe(false)
    expect(wrapper.find('#attendee-badge').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).not.toContain('Salvar dados')
  })

  it('erro de validação aparece no campo', async () => {
    const save = vi.fn().mockResolvedValue({ ok: false, message: 'Use até 60 caracteres.', fieldErrors: { badgeName: 'Use até 60 caracteres.' } })
    const wrapper = await mountSuspended(AttendeeDrawer, { props: { modelValue: true, detail, state: 'ready', disabled: false, canSeePrivate: true, save, resend: vi.fn() } })

    await wrapper.find('#attendee-form').trigger('submit')
    await tick()
    expect(wrapper.find('#attendee-badge').attributes('aria-invalid')).toBe('true')
  })
})

describe('ReminderModal', () => {
  it('mostra quantas pessoas recebem e a prévia, e manda o número confirmado', async () => {
    const preview = vi.fn().mockResolvedValue({ recipients: 3, subject: 'Complete seus dados para o crachá do PHPeste 2027', html: '<p>Oi</p>' })
    const remind = vi.fn().mockResolvedValue({ ok: true })
    const wrapper = await mountSuspended(ReminderModal, { props: { modelValue: false, preview, remind } })
    await wrapper.setProps({ modelValue: true })
    await tick()

    const box = wrapper.find('[data-testid="reminder-preview"]')
    expect(flat(box.text())).toContain('3 pessoas com ingresso válido')
    expect(flat(box.text())).toContain('Assunto: Complete seus dados para o crachá do PHPeste 2027')
    expect(box.find('iframe').attributes('sandbox')).toBe('')

    await wrapper.findAll('button').find((b) => b.text().includes('Enviar para 3 pessoas'))!.trigger('click')
    expect(remind).toHaveBeenCalledWith(3)
  })

  it('sem ninguém com dados faltando, não oferece envio', async () => {
    const wrapper = await mountSuspended(ReminderModal, { props: { modelValue: false, preview: vi.fn().mockResolvedValue({ recipients: 0, subject: null, html: null }), remind: vi.fn() } })
    await wrapper.setProps({ modelValue: true })
    await tick()

    expect(wrapper.text()).toContain('Todo mundo já completou os dados.')
    expect(wrapper.text()).not.toContain('Enviar para')
  })
})
