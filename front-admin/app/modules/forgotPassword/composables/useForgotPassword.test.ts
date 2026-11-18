import { beforeEach, describe, expect, it, vi } from 'vitest'
import forgotPasswordService from '../services/forgotPasswordService'
import { ForgotPasswordError } from '../services/forgotPasswordError'
import { useForgotPassword } from './useForgotPassword'

vi.mock('../services/forgotPasswordService', () => ({ default: { requestLink: vi.fn() } }))

beforeEach(() => vi.clearAllMocks())

describe('useForgotPassword', () => {
  it('shows the same confirmation whether the e-mail exists or not', async () => {
    vi.mocked(forgotPasswordService.requestLink).mockResolvedValueOnce({ sent: true, message: 'ok' })

    const { submit, sent, errorMessage } = useForgotPassword()
    await submit('ninguem@example.com')

    expect(sent.value).toBe(true)
    expect(errorMessage.value).toBeNull()
  })

  it('does not reveal anything on a validation error either', async () => {
    vi.mocked(forgotPasswordService.requestLink).mockRejectedValueOnce(new ForgotPasswordError('invalid', 422))

    const { submit, sent } = useForgotPassword()
    await submit('x@x')

    expect(sent.value).toBe(true)
  })

  it('asks to wait when rate limited and falls back to a generic message otherwise', async () => {
    vi.mocked(forgotPasswordService.requestLink).mockRejectedValueOnce(new ForgotPasswordError('too many', 429))
    const first = useForgotPassword()
    await first.submit('ada@example.com')
    expect(first.errorMessage.value).toBe('Muitos pedidos seguidos. Espere um pouco e tente de novo.')
    expect(first.sent.value).toBe(false)

    vi.mocked(forgotPasswordService.requestLink).mockRejectedValueOnce(new ForgotPasswordError('down', null))
    const second = useForgotPassword()
    await second.submit('ada@example.com')
    expect(second.errorMessage.value).toBe('Não deu para enviar o link agora. Tente de novo.')
  })
})
