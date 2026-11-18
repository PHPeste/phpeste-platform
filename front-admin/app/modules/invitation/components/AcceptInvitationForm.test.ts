import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { useInvitation } from '../composables/useInvitation'
import AcceptInvitationForm from './AcceptInvitationForm.vue'
import type { Invitation } from '../types'

vi.mock('../composables/useInvitation', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../composables/useInvitation')>()),
  useInvitation: vi.fn(),
}))

const base: Invitation = {
  editionId: 'ed-1',
  editionName: 'PHPeste 2027',
  email: 'livia@comunidade.dev',
  name: 'Lívia Ferraz',
  roleLabel: 'Financeiro',
  invitedBy: 'Mariana Sales',
  accountExists: false,
  expiresAt: new Date('2027-11-10T15:00:00Z'),
}

function mockComposable(invitation: Invitation | null, state = 'ready') {
  const accept = vi.fn().mockResolvedValue(true)
  vi.mocked(useInvitation).mockReturnValue({
    state: ref(state),
    invitation: ref(invitation),
    goneMessage: ref('Este convite já foi usado.'),
    isSubmitting: ref(false),
    errorMessage: ref(null),
    fieldErrors: ref(null),
    load: vi.fn(),
    accept,
  } as unknown as ReturnType<typeof useInvitation>)
  return accept
}

beforeEach(() => {
  vi.clearAllMocks()
  useAuthStore().clearSession()
})

describe('AcceptInvitationForm', () => {
  it('shows who invited, the role and the edition, with the e-mail fixed', async () => {
    mockComposable(base)
    const wrapper = await mountSuspended(AcceptInvitationForm, { props: { token: 'tok' } })

    expect(wrapper.text()).toContain('Mariana Sales convidou você como Financeiro no PHPeste 2027.')
    expect(wrapper.find('#invitation-email').attributes('readonly')).toBeDefined()
    expect(wrapper.text()).toContain('Convite válido até 10 nov 2027.')
  })

  it('creates the account with name and a confirmed password of 10 characters', async () => {
    const accept = mockComposable(base)
    const wrapper = await mountSuspended(AcceptInvitationForm, { props: { token: 'tok' } })

    await wrapper.find('#invitation-password').setValue('curta')
    await wrapper.find('form').trigger('submit.prevent')
    expect(wrapper.text()).toContain('A senha precisa ter pelo menos 10 caracteres.')
    expect(accept).not.toHaveBeenCalled()

    await wrapper.find('#invitation-name').setValue('Lívia Ferraz')
    await wrapper.find('#invitation-password').setValue('senha123456')
    await wrapper.find('#invitation-password-confirmation').setValue('senha123456')
    await wrapper.find('form').trigger('submit.prevent')
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(accept).toHaveBeenCalledWith({ token: 'tok', name: 'Lívia Ferraz', password: 'senha123456', passwordConfirmation: 'senha123456' })
    expect(wrapper.emitted('accepted')).toHaveLength(1)
  })

  it('asks only for the password when the e-mail already has an account', async () => {
    const accept = mockComposable({ ...base, accountExists: true })
    const wrapper = await mountSuspended(AcceptInvitationForm, { props: { token: 'tok' } })

    expect(wrapper.find('#invitation-name').exists()).toBe(false)
    expect(wrapper.text()).toContain('Sua senha')

    await wrapper.find('#invitation-password').setValue('minhasenha')
    await wrapper.find('form').trigger('submit.prevent')

    expect(accept).toHaveBeenCalledWith({ token: 'tok', password: 'minhasenha' })
  })

  it('accepts directly when already signed in with the invited e-mail, and warns about another account', async () => {
    const accept = mockComposable(base)
    useAuthStore().setSession({ id: 'u', name: 'Lívia', email: 'Livia@Comunidade.dev', avatarUrl: null }, 't')
    const wrapper = await mountSuspended(AcceptInvitationForm, { props: { token: 'tok' } })

    expect(wrapper.find('#invitation-password').exists()).toBe(false)
    await wrapper.find('form').trigger('submit.prevent')
    expect(accept).toHaveBeenCalledWith({ token: 'tok' })

    useAuthStore().setSession({ id: 'u2', name: 'Outra', email: 'outra@example.com', avatarUrl: null }, 't')
    const other = await mountSuspended(AcceptInvitationForm, { props: { token: 'tok' } })
    expect(other.text()).toContain('mas o convite é para livia@comunidade.dev')
  })

  it('explains a used or expired invitation', async () => {
    mockComposable(null, 'gone')
    const wrapper = await mountSuspended(AcceptInvitationForm, { props: { token: 'tok' } })

    expect(wrapper.find('[role="alert"]').text()).toBe('Este convite já foi usado.')
    expect(wrapper.text()).toContain('Peça um novo convite')
  })
})
