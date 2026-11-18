import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useResetPassword } from '../composables/useResetPassword'
import ResetPasswordForm from './ResetPasswordForm.vue'

vi.mock('../composables/useResetPassword', () => ({ useResetPassword: vi.fn() }))

function mockComposable(overrides: { done?: boolean, invalidLink?: boolean, submit?: ReturnType<typeof vi.fn> } = {}) {
  const submit = overrides.submit ?? vi.fn()
  vi.mocked(useResetPassword).mockReturnValue({
    isSubmitting: ref(false),
    done: ref(overrides.done ?? false),
    invalidLink: ref(overrides.invalidLink ?? false),
    errorMessage: ref(null),
    passwordError: ref(null),
    submit,
  } as unknown as ReturnType<typeof useResetPassword>)
  return submit
}

describe('ResetPasswordForm', () => {
  it('treats a link without token or e-mail as invalid', async () => {
    mockComposable()
    const wrapper = await mountSuspended(ResetPasswordForm, { props: { email: null, token: 'tok' } })

    expect(wrapper.text()).toContain('Este link de redefinição é inválido ou expirou.')
    expect(wrapper.find('a[href="/esqueci-senha"]').exists()).toBe(true)
  })

  it('requires 10 characters and matching passwords before sending', async () => {
    const submit = mockComposable()
    const wrapper = await mountSuspended(ResetPasswordForm, { props: { email: 'ada@example.com', token: 'tok' } })

    await wrapper.find('#new-password').setValue('curta')
    await wrapper.find('#new-password-confirmation').setValue('outra')
    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('A senha precisa ter pelo menos 10 caracteres.')
    expect(wrapper.text()).toContain('As senhas não são iguais.')
    expect(submit).not.toHaveBeenCalled()

    await wrapper.find('#new-password').setValue('senhanova123')
    await wrapper.find('#new-password-confirmation').setValue('senhanova123')
    await wrapper.find('form').trigger('submit.prevent')

    expect(submit).toHaveBeenCalledWith({ email: 'ada@example.com', token: 'tok', password: 'senhanova123', passwordConfirmation: 'senhanova123' })
  })

  it('confirms the change and points to the login', async () => {
    mockComposable({ done: true })
    const wrapper = await mountSuspended(ResetPasswordForm, { props: { email: 'ada@example.com', token: 'tok' } })

    expect(wrapper.find('[role="status"]').text()).toContain('Senha alterada')
    expect(wrapper.find('a[href="/login"]').exists()).toBe(true)
  })
})
