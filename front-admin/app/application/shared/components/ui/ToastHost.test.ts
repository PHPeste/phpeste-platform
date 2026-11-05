import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import { useToastStore } from '@/stores/toastStore'
import ToastHost from './ToastHost.vue'

beforeEach(() => {
  vi.useRealTimers()
  // O store do Nuxt é compartilhado entre os testes: limpa os avisos anteriores.
  useToastStore().items.splice(0)
})

describe('PhToastHost + toastStore', () => {
  it('é uma região viva e mostra o aviso disparado', async () => {
    const wrapper = await mountSuspended(ToastHost)
    useToast().success('Identidade publicada.')
    await nextTick()
    expect(wrapper.find('[aria-live="polite"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Identidade publicada.')
  })

  it('erro usa role="alert" e os demais "status"', async () => {
    const wrapper = await mountSuspended(ToastHost)
    const toast = useToast()
    toast.error('Falha ao enviar.')
    toast.info('Salvo.')
    await nextTick()
    const roles = wrapper.findAll('.alert').map((a) => a.attributes('role'))
    expect(roles).toEqual(['alert', 'status'])
  })

  it('dispensa o aviso pelo botão', async () => {
    const wrapper = await mountSuspended(ToastHost)
    useToast().info('Salvo.')
    await nextTick()
    await wrapper.find('button').trigger('click')
    expect(wrapper.text()).not.toContain('Salvo.')
  })

  it('executa a ação ("Desfazer") e remove o aviso', async () => {
    const run = vi.fn()
    const wrapper = await mountSuspended(ToastHost)
    useToast().success('Publicada.', { label: 'Desfazer', run })
    await nextTick()
    const undo = wrapper.findAll('button').find((b) => b.text() === 'Desfazer')!
    await undo.trigger('click')
    expect(run).toHaveBeenCalledOnce()
    expect(useToastStore().items).toHaveLength(0)
  })

  it('some sozinho depois do tempo', async () => {
    vi.useFakeTimers()
    useToast().info('Salvo.')
    expect(useToastStore().items).toHaveLength(1)
    vi.advanceTimersByTime(5001)
    expect(useToastStore().items).toHaveLength(0)
  })
})
