import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import teamService from './teamService'
import { TeamError } from './teamError'

vi.mock('@/infrastructure/http/HttpClient', () => ({
  httpClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  HttpClientError: class extends Error {
    statusCode: number | null
    fieldErrors: Record<string, string[]> | null

    constructor(message: string, statusCode: number | null = null, fieldErrors: Record<string, string[]> | null = null) {
      super(message)
      this.statusCode = statusCode
      this.fieldErrors = fieldErrors
    }
  },
}))

describe('teamService', () => {
  it('chama as rotas da equipe da edição', async () => {
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.patch).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.delete).mockResolvedValue(null)
    vi.mocked(httpClient.get).mockResolvedValue({ data: {} })

    await teamService.list('ed-1')
    await teamService.roles()
    await teamService.invite('ed-1', { email: 'a@b.dev', name: '', role: 'avaliador' })
    await teamService.resendInvitation('ed-1', 'inv-1')
    await teamService.cancelInvitation('ed-1', 'inv-1')
    await teamService.changeRole('ed-1', 'm-1', 'financeiro')
    await teamService.removeMember('ed-1', 'm-1')

    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/team')
    expect(httpClient.get).toHaveBeenCalledWith('/roles')
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/team/invitations', { body: { email: 'a@b.dev', name: null, role: 'avaliador' } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/team/invitations/inv-1/resend')
    expect(httpClient.delete).toHaveBeenCalledWith('/editions/ed-1/team/invitations/inv-1')
    expect(httpClient.patch).toHaveBeenCalledWith('/editions/ed-1/team/members/m-1', { body: { role: 'financeiro' } })
    expect(httpClient.delete).toHaveBeenCalledWith('/editions/ed-1/team/members/m-1')
  })

  it('embrulha o erro da API num TeamError com status e campos', async () => {
    vi.mocked(httpClient.patch).mockRejectedValueOnce(new HttpClientError('x', 422, { role: ['A edição precisa de pelo menos uma pessoa organizadora.'] }))

    const error = await teamService.changeRole('ed-1', 'm-1', 'financeiro').catch((e: unknown) => e)
    expect(error).toBeInstanceOf(TeamError)
    expect(error).toMatchObject({ statusCode: 422, fieldErrors: { role: ['A edição precisa de pelo menos uma pessoa organizadora.'] } })
  })
})
