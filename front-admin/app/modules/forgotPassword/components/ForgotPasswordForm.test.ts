import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useForgotPassword } from '../composables/useForgotPassword'
import ForgotPasswordForm from './ForgotPasswordForm.vue'

vi.mock('../composables/useForgotPassword', () => ({ useForgotPassword: vi.fn() }))

function mockComposable(overrides: { sent?: boolean, submit?: ReturnType<typeof vi.fn> } = {}) {
  const submit = overrides.submit ?? vi.fn()
  vi.mocked(useForgotPassword).mockReturnValue({
    isSubmitting: ref(false),
    sent: ref(overrides.sent ?? false),
    errorMessage: ref(null),
    submit,
  } as unknown as ReturnType<typeof useForgotPassword>)
  return submit
}

describe('ForgotPasswordForm', () => {
  it('validates the e-mail before sending', async () => {
    const submit = mockComposable()
    const wrapper = await mountSuspended(ForgotPasswordForm)

    await wrapper.find('form').trigger('submit.prevent')
    expect(wrapper.text()).toContain('Informe o e-mail.')
    expect(submit).not.toHaveBeenCalled()

    await wrapper.find('#email').setValue(' ada@example.com ')
    await wrapper.find('form').trigger('submit.prevent')
    expect(submit).toHaveBeenCalledWith('ada@example.com')
  })

  it('shows the neutral confirmation after sending', async () => {
    mockComposable({ sent: true })
    const wrapper = await mountSuspended(ForgotPasswordForm)

    expect(wrapper.find('[role="status"]').text()).toContain('Se existir uma conta com este e-mail')
  })
})
