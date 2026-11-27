import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import HostedCardPayment from './HostedCardPayment.vue'
import PixPayment from './PixPayment.vue'
import ReceiverNotice from './ReceiverNotice.vue'
import TicketQr from './TicketQr.vue'
import { translatePayment, translateReceiver } from '../composables/translate'
import { paymentPayload, receiverPayload } from '../composables/fixtures'

const flat = (text: string) => text.replace(/\s/g, ' ')

describe('ReceiverNotice', () => {
  it('nome de quem recebe, pessoa ou empresa, e aviso de ambiente de teste', async () => {
    const person = await mountSuspended(ReceiverNotice, { props: { receiver: translateReceiver(receiverPayload({ sandbox: true })), editionName: 'PHPeste 2027' } })
    expect(person.text()).toContain('Pagamento para Fulana de Tal, responsável financeiro do PHPeste 2027.')
    expect(person.text()).toContain('nenhum valor é cobrado')

    const company = await mountSuspended(ReceiverNotice, { props: { receiver: translateReceiver(receiverPayload({ holder_type: 'company', holder_name: 'Associação PHP-PB' })), editionName: 'PHPeste 2027' } })
    expect(company.text()).toContain('Pagamento para Associação PHP-PB, que recebe pelo PHPeste 2027.')
  })
})

describe('PixPayment', () => {
  it('QR com texto alternativo, código para copiar com um toque e validade', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { ...navigator, clipboard: { writeText } })
    const wrapper = await mountSuspended(PixPayment, { props: { payment: translatePayment(paymentPayload()), checking: false } })

    expect(flat(wrapper.find('img').attributes('alt')!)).toBe('QR code do Pix de R$ 280,80')
    expect((wrapper.find('#pix-code').element as HTMLTextAreaElement).value).toBe('00020126pix-copia-e-cola')
    expect(wrapper.text()).toMatch(/O código vale até \d{2}:\d{2}\./)

    await wrapper.find('button').trigger('click')
    await vi.waitFor(() => expect(wrapper.find('[data-testid="pix-copy-status"]').text()).toBe('Código copiado.'))
    expect(writeText).toHaveBeenCalledWith('00020126pix-copia-e-cola')
    vi.unstubAllGlobals()
  })
})

describe('HostedCardPayment', () => {
  it('parcelas com o valor de cada uma', async () => {
    const wrapper = await mountSuspended(HostedCardPayment, { props: { totalCents: 30000, maxInstallments: 3, busy: false } })
    expect(wrapper.findAll('option').map((option) => flat(option.text()))).toEqual(['À vista: R$ 300,00', '2× de R$ 150,00', '3× de R$ 100,00'])

    await wrapper.find('select').setValue('3')
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('pay')).toEqual([[3]])
  })
})

describe('TicketQr', () => {
  it('desenha o QR como SVG com nome acessível', async () => {
    const wrapper = await mountSuspended(TicketQr, { props: { value: 'PHT1.tk-1.1.abc', label: 'QR code do ingresso de Ada' } })
    const svg = wrapper.find('svg')
    expect(svg.attributes('aria-label')).toBe('QR code do ingresso de Ada')
    expect(svg.attributes('viewBox')).toMatch(/^0 0 \d+ \d+$/)
    expect(wrapper.find('path').attributes('d')!.length).toBeGreaterThan(100)
  })
})
