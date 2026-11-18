import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '@/stores/authStore'
import invitationService from '../services/invitationService'
import { InvitationError } from '../services/invitationError'
import { formatInvitationDate, useInvitation } from './useInvitation'

vi.mock('../services/invitationService', () => ({ default: { show: vi.fn(), accept: vi.fn() } }))

const payload = {
  data: {
    edition: { id: 'ed-1', name: 'PHPeste 2027' },
    email: 'livia@comunidade.dev',
    name: 'Lívia Ferraz',
    role: 'financeiro',
    role_label: 'Financeiro',
    invited_by: 'Mariana Sales',
    account_exists: false,
    expires_at: '2027-11-10T15:00:00Z',
  },
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useInvitation', () => {
  it('translates the invitation for the screen', async () => {
    vi.mocked(invitationService.show).mockResolvedValueOnce(payload)

    const { load, state, invitation } = useInvitation()
    await load('tok')

    expect(state.value).toBe('ready')
    expect(invitation.value).toMatchObject({ editionName: 'PHPeste 2027', roleLabel: 'Financeiro', invitedBy: 'Mariana Sales', accountExists: false })
    expect(formatInvitationDate(invitation.value!.expiresAt)).toBe('10 nov 2027')
  })

  it('distinguishes a missing invitation from a used, expired or cancelled one', async () => {
    vi.mocked(invitationService.show).mockRejectedValueOnce(new InvitationError('not found', 404))
    const missing = useInvitation()
    await missing.load('x')
    expect(missing.state.value).toBe('not_found')

    vi.mocked(invitationService.show).mockRejectedValueOnce(new InvitationError('Este convite expirou. Peça um novo para a organização.', 410))
    const gone = useInvitation()
    await gone.load('x')
    expect(gone.state.value).toBe('gone')
    expect(gone.goneMessage.value).toBe('Este convite expirou. Peça um novo para a organização.')
  })

  it('saves the session when accepting creates or links the account', async () => {
    vi.mocked(invitationService.accept).mockResolvedValueOnce({ user: { id: 'user-9', name: 'Lívia', email: 'livia@comunidade.dev' }, edition_id: 'ed-1', token: 'new-token' })

    expect(await useInvitation().accept({ token: 'tok', name: 'Lívia', password: 'senha123456', passwordConfirmation: 'senha123456' })).toBe(true)
    expect(useAuthStore().token).toBe('new-token')
  })

  it('shows the field error from the API, like a wrong password', async () => {
    vi.mocked(invitationService.accept).mockRejectedValueOnce(new InvitationError('x', 422, { password: ['Senha incorreta para a conta deste e-mail.'] }))

    const { accept, errorMessage, fieldErrors } = useInvitation()
    expect(await accept({ token: 'tok', password: 'errada' })).toBe(false)
    expect(errorMessage.value).toBe('Senha incorreta para a conta deste e-mail.')
    expect(fieldErrors.value).toEqual({ password: ['Senha incorreta para a conta deste e-mail.'] })
  })
})
