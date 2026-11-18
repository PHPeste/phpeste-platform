import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '@/stores/authStore'
import myProfileService from '../services/myProfileService'
import { MyProfileError } from '../services/myProfileError'
import { useMyProfile } from './useMyProfile'

vi.mock('../services/myProfileService', () => ({
  default: { me: vi.fn(), update: vi.fn(), uploadPhoto: vi.fn(), removePhoto: vi.fn(), changePassword: vi.fn(), sessions: vi.fn(), endSession: vi.fn() },
}))

const user = (overrides: Record<string, unknown> = {}) => ({
  id: 'u-1', name: 'Ana Martins', email: 'ana@x.dev', avatar_url: null, locale: 'pt_BR', has_password: true, providers: [],
  notifications: [{ id: 'weekly_summary', label: 'Resumo semanal da edição', description: 'Toda segunda.', enabled: false }],
  ...overrides,
})
const sessions = { data: [{ id: '1', name: 'Painel PHPeste', current: true, last_used_at: null, created_at: null }, { id: '2', name: 'App · Android', current: false, last_used_at: '2027-03-09T12:00:00Z', created_at: null }] }

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  vi.mocked(myProfileService.me).mockResolvedValue({ user: user() })
  vi.mocked(myProfileService.sessions).mockResolvedValue(sessions)
  useAuthStore().setSession({ id: 'u-1', name: 'Ana', email: 'ana@x.dev', avatarUrl: null }, 'tok', true)
})

describe('useMyProfile', () => {
  it('carrega conta, notificações e sessões, e salva o nome refletindo no cabeçalho', async () => {
    vi.mocked(myProfileService.update).mockResolvedValueOnce({ data: user({ name: 'Ana Martins Lima' }) })
    const profile = useMyProfile()
    await profile.load()

    expect(profile.sessions.value.map((session) => session.current)).toEqual([true, false])
    profile.form.name = ' Ana Martins Lima '
    expect(profile.isDirty()).toBe(true)
    expect(await profile.saveAccount()).toEqual({ ok: true })
    expect(myProfileService.update).toHaveBeenCalledWith({ name: 'Ana Martins Lima', locale: 'pt_BR' })
    expect(useAuthStore().user?.name).toBe('Ana Martins Lima')
    expect(JSON.parse(localStorage.getItem('app.auth')!).user.name).toBe('Ana Martins Lima')
  })

  it('liga uma notificação mandando só ela', async () => {
    vi.mocked(myProfileService.update).mockResolvedValueOnce({ data: user({ notifications: [{ id: 'weekly_summary', label: 'Resumo semanal da edição', description: 'Toda segunda.', enabled: true }] }) })
    const profile = useMyProfile()
    await profile.load()

    await profile.setNotification('weekly_summary', true)
    expect(myProfileService.update).toHaveBeenCalledWith({ notifications: { weekly_summary: true } })
    expect(profile.account.value?.notifications[0]?.enabled).toBe(true)
  })

  it('troca a senha, recarrega as sessões e mostra o erro da senha atual', async () => {
    vi.mocked(myProfileService.changePassword).mockRejectedValueOnce(new MyProfileError('x', 422, { current_password: ['A senha atual está incorreta.'] })).mockResolvedValueOnce({ updated: true })
    const profile = useMyProfile()
    await profile.load()

    expect(await profile.changePassword({ currentPassword: 'a', password: 'b', passwordConfirmation: 'c' })).toMatchObject({ ok: false, fieldErrors: { passwordConfirmation: 'As senhas não são iguais.' } })
    expect(await profile.changePassword({ currentPassword: 'errada', password: 'nova-senha-forte', passwordConfirmation: 'nova-senha-forte' })).toMatchObject({ ok: false, fieldErrors: { currentPassword: 'A senha atual está incorreta.' } })
    expect(await profile.changePassword({ currentPassword: 'certa', password: 'nova-senha-forte', passwordConfirmation: 'nova-senha-forte' })).toEqual({ ok: true })
    expect(myProfileService.changePassword).toHaveBeenLastCalledWith({ current_password: 'certa', password: 'nova-senha-forte', password_confirmation: 'nova-senha-forte' })
    expect(myProfileService.sessions).toHaveBeenCalledTimes(2)
  })

  it('conta só com GitHub define a senha sem a atual', async () => {
    vi.mocked(myProfileService.me).mockResolvedValueOnce({ user: user({ has_password: false, providers: ['github'] }) })
    vi.mocked(myProfileService.changePassword).mockResolvedValueOnce({ updated: true })
    const profile = useMyProfile()
    await profile.load()

    await profile.changePassword({ currentPassword: '', password: 'nova-senha-forte', passwordConfirmation: 'nova-senha-forte' })
    expect(myProfileService.changePassword).toHaveBeenCalledWith({ current_password: null, password: 'nova-senha-forte', password_confirmation: 'nova-senha-forte' })
    expect(profile.account.value?.hasPassword).toBe(true)
  })

  it('encerra a sessão de outro dispositivo', async () => {
    vi.mocked(myProfileService.endSession).mockResolvedValueOnce(null)
    const profile = useMyProfile()
    await profile.load()

    expect(await profile.endSession(profile.sessions.value[1]!)).toEqual({ ok: true })
    expect(myProfileService.endSession).toHaveBeenCalledWith('2')
    expect(profile.sessions.value.map((session) => session.id)).toEqual(['1'])
  })
})
