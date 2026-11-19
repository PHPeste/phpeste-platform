import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import AccountForm from './AccountForm.vue'
import ReceiverPreview from './ReceiverPreview.vue'
import type { Driver } from '../types'

const drivers: Driver[] = [{ key: 'asaas', label: 'Asaas', needsPublicKey: false }, { key: 'mercadopago', label: 'Mercado Pago', needsPublicKey: true }]
const tick = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('ReceiverPreview', () => {
  it('mostra o recebedor, os métodos com parcelamento e o aviso de sandbox', async () => {
    const wrapper = await mountSuspended(ReceiverPreview, { props: { holderName: 'Associação PHP-PB', holderType: 'company', methods: ['pix', 'card'], maxInstallments: 3, sandbox: true } })
    const text = wrapper.text()
    expect(text).toContain('Associação PHP-PB')
    expect(text).toContain('Pessoa jurídica (CNPJ)')
    expect(text).toContain('Cartão de crédito (até 3x)')
    expect(text).toContain('nenhuma cobrança é real')
  })
})

describe('AccountForm', () => {
  it('campos de chave são senha, a orientação muda com o titular e a chave pública só aparece quando o gateway pede', async () => {
    const create = vi.fn().mockResolvedValue({ ok: true })
    const wrapper = await mountSuspended(AccountForm, { props: { modelValue: false, drivers, current: null, create } })
    await wrapper.setProps({ modelValue: true })

    expect(wrapper.findAll('input[type="password"]')).toHaveLength(2)
    expect(wrapper.find('[data-testid="holder-guidance"]').text()).toContain('carnê-leão')

    const selects = wrapper.findAll('select')
    await selects[0]!.setValue('mercadopago')
    expect(wrapper.findAll('input[type="password"]')).toHaveLength(3)

    await wrapper.findAll('select').find((select) => select.html().includes('company'))!.setValue('company')
    expect(wrapper.find('[data-testid="holder-guidance"]').text()).toContain('faturamento da empresa')
  })

  it('pede confirmação quando a troca afeta pedidos pagos e manda de novo com confirm', async () => {
    const create = vi.fn()
      .mockResolvedValueOnce({ ok: false, message: 'x', fieldErrors: { confirm: 'Esta conta já recebeu 3 pedidos pagos.' }, needsConfirm: true })
      .mockResolvedValueOnce({ ok: true })
    const current = { id: 'a-1', driver: 'asaas', driverLabel: 'Asaas', environment: 'sandbox', environmentLabel: '', credentialsHint: '••••abcd', holderType: 'individual', holderTypeLabel: '', holderName: 'Fulana', holderDocument: '', methods: ['pix'], methodLabels: [], maxInstallments: 1, pixExpirationMinutes: 30, active: true, ready: true, verifiedAt: null, webhookVerifiedAt: null, webhookRejections: 0, webhookRejectedAt: null, retiredAt: null, createdAt: '', webhookUrl: null, lastEventAt: null } as const
    const wrapper = await mountSuspended(AccountForm, { props: { modelValue: false, drivers, current: { ...current, methods: ['pix'], methodLabels: [] }, create }, attachTo: document.body })
    await wrapper.setProps({ modelValue: true })

    expect(wrapper.find('[data-testid="replace-warning"]').exists()).toBe(true)
    await wrapper.find('form').trigger('submit')
    await tick()
    expect(document.body.textContent).toContain('Esta conta já recebeu 3 pedidos pagos.')

    const confirm = [...document.querySelectorAll('button')].find((button) => button.textContent?.includes('Trocar de conta'))!
    confirm.click()
    await tick()
    expect(create).toHaveBeenLastCalledWith(expect.objectContaining({ holderName: 'Fulana' }), true)
    wrapper.unmount()
  })
})
