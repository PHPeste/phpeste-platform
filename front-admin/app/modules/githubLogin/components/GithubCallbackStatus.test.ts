import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useGithubCallback } from '../composables/useGithubCallback'
import GithubCallbackStatus from './GithubCallbackStatus.vue'

vi.mock('../composables/useGithubCallback', () => ({ useGithubCallback: vi.fn() }))

function mockComposable(result = true) {
  const submit = vi.fn().mockResolvedValue(result)
  vi.mocked(useGithubCallback).mockReturnValue({ isSubmitting: ref(false), errorMessage: ref(null), fieldErrors: ref(null), submit } as unknown as ReturnType<typeof useGithubCallback>)
  return submit
}

describe('GithubCallbackStatus', () => {
  it('exchanges the code and emits success', async () => {
    const submit = mockComposable()
    const wrapper = await mountSuspended(GithubCallbackStatus, { props: { code: 'c', state: 's', providerError: null } })
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(submit).toHaveBeenCalledWith({ code: 'c', state: 's' })
    expect(wrapper.emitted('success')).toHaveLength(1)
  })

  it('does not call the backend when the person cancelled on GitHub or the params are missing', async () => {
    const submit = mockComposable()

    const cancelled = await mountSuspended(GithubCallbackStatus, { props: { code: null, state: null, providerError: 'access_denied' } })
    expect(cancelled.text()).toContain('O acesso ao GitHub foi cancelado.')

    const missing = await mountSuspended(GithubCallbackStatus, { props: { code: 'c', state: null, providerError: null } })
    expect(missing.text()).toContain('O GitHub não devolveu os dados do login.')

    expect(submit).not.toHaveBeenCalled()
  })
})
