import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Nome Sobrenome', email: 'voce@comunidade.dev', avatarUrl: null }, token: 'e2e-token' }
const edition = {
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHPeste', slug: 'phpeste' },
  status: 'cfp_open', status_label: 'CFP aberto', starts_on: null, ends_on: null, city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role: 'organizador',
}
const member = (id: string, userId: string, name: string, email: string, role: string, label: string, access: string, seen: string | null) =>
  ({ id, user_id: userId, name, email, role, role_label: label, access, last_seen_at: seen, joined_at: '2027-01-01T00:00:00Z' })
const me = member('m-me', 'u-me', 'Nome Sobrenome', 'voce@comunidade.dev', 'organizador', 'Organizador', 'Tudo na edição', new Date().toISOString())
const joao = member('m-joao', 'u-joao', 'João Paulo', 'joao@comunidade.dev', 'avaliador', 'Revisor do CFP', 'CFP e palestrantes', null)
const thiago = { id: 'inv-1', email: 'thiago@comunidade.dev', name: 'Thiago Braga', role: 'checkin', role_label: 'Credenciamento', expires_at: '2027-03-17T00:00:00Z', created_at: '2027-03-10T00:00:00Z' }
const roles = {
  data: {
    roles: [
      { id: 'organizador', label: 'Organizador', access: 'Tudo na edição', permissions: ['edition.manage', 'cfp.review', 'checkin.do'] },
      { id: 'avaliador', label: 'Revisor do CFP', access: 'CFP e palestrantes', permissions: ['cfp.review'] },
      { id: 'checkin', label: 'Credenciamento', access: 'Check-in', permissions: ['checkin.do'] },
    ],
    permissions: [{ id: 'edition.manage', label: 'Configurar edição e identidade' }, { id: 'cfp.review', label: 'Avaliar propostas' }, { id: 'checkin.do', label: 'Fazer check-in' }],
  },
}

function teamPayload(members: unknown[], invitations: unknown[]) {
  const list = members as Array<{ role: string }>
  return {
    data: {
      members, invitations,
      summary: { people: list.length, pending_invitations: invitations.length, by_role: { organizador: list.filter((m) => m.role === 'organizador').length, avaliador: list.filter((m) => m.role === 'avaliador').length, financeiro: 0, checkin: 0, voluntario: 0 } },
    },
  }
}

async function openTeam(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, team: { members: unknown[], invitations: unknown[] }, handlers: Record<string, (route: Route) => Promise<void> | void> = {}) {
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition] } }))
  await page.route('**/api/v1/roles', (route) => route.fulfill({ status: 200, json: roles }))
  await page.route('**/api/v1/editions/ed-27/team**', async (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1/editions/ed-27/team', '') || '/'
    const handler = handlers[`${route.request().method()} ${path}`]
    if (handler) return handler(route)
    return route.fulfill({ status: 200, json: teamPayload(team.members, team.invitations) })
  })
  await goto('/equipe', { waitUntil: 'hydration' })
  await expect(page.getByRole('heading', { name: 'Equipe e papéis', level: 1 })).toBeVisible()
}

test.describe('Equipe e papéis', () => {
  test('lista a equipe, os convites pendentes e a tabela de papéis vinda da API', async ({ page, goto }) => {
    await openTeam(page, goto, { members: [me, joao], invitations: [thiago] })

    const table = page.getByRole('table', { name: 'Equipe da edição' })
    await expect(table.getByRole('row', { name: /Nome Sobrenome \(você\).*Organizador.*Tudo na edição.*agora/ })).toBeVisible()
    await expect(table.getByRole('row', { name: /João Paulo.*Revisor do CFP.*CFP e palestrantes.*nunca/ })).toBeVisible()
    await expect(table.getByRole('row', { name: /Thiago Braga.*Credenciamento.*Convite pendente/ })).toBeVisible()
    await expect(page.getByRole('region', { name: 'Resumo da equipe' })).toContainText('1 convite pendente')

    const matrix = page.getByRole('table', { name: 'O que cada papel pode fazer' })
    await expect(matrix.getByRole('columnheader')).toHaveText(['Permissão', 'Organizador', 'Revisor do CFP', 'Credenciamento'])
    await expect(matrix.getByRole('row', { name: /Fazer check-in/ }).getByRole('cell')).toHaveText(['✓Pode', '—Não pode', '✓Pode'])
  })

  test('convida por e-mail com papel e mostra o aviso de sucesso', async ({ page, goto }) => {
    const team = { members: [me], invitations: [] as unknown[] }
    await openTeam(page, goto, team, {
      'POST /invitations': async (route) => {
        expect(route.request().postDataJSON()).toEqual({ email: 'livia@comunidade.dev', name: 'Lívia Ferraz', role: 'checkin' })
        team.invitations.push({ ...thiago, id: 'inv-2', email: 'livia@comunidade.dev', name: 'Lívia Ferraz' })
        await route.fulfill({ status: 201, json: { data: team.invitations[0] } })
      },
    })

    await page.getByRole('button', { name: 'Convidar pessoa' }).click()
    const dialog = page.getByRole('dialog', { name: 'Convidar pessoa' })
    await dialog.getByRole('button', { name: 'Enviar convite' }).click()
    await expect(dialog.getByText('Informe um e-mail válido.')).toBeVisible()

    await dialog.getByLabel('E-mail').fill('livia@comunidade.dev')
    await dialog.getByLabel('Nome (opcional)').fill('Lívia Ferraz')
    await dialog.getByLabel('Papel').selectOption('checkin')
    await dialog.getByRole('button', { name: 'Enviar convite' }).click()

    await expect(page.getByText('Convite enviado para livia@comunidade.dev.')).toBeVisible()
    await expect(page.getByRole('row', { name: /Lívia Ferraz.*Convite pendente/ })).toBeVisible()
  })

  test('reenvia e cancela convite pendente com confirmação', async ({ page, goto }) => {
    const team = { members: [me], invitations: [thiago] as unknown[] }
    await openTeam(page, goto, team, {
      'POST /invitations/inv-1/resend': (route) => route.fulfill({ status: 200, json: { data: thiago } }),
      'DELETE /invitations/inv-1': async (route) => {
        team.invitations = []
        await route.fulfill({ status: 204 })
      },
    })

    await page.getByRole('button', { name: 'Reenviar thiago@comunidade.dev' }).click()
    await expect(page.getByText('Convite reenviado para thiago@comunidade.dev.')).toBeVisible()

    await page.getByRole('button', { name: 'Cancelar convite thiago@comunidade.dev' }).click()
    const dialog = page.getByRole('dialog', { name: 'Cancelar convite' })
    await expect(dialog).toContainText('O link enviado para thiago@comunidade.dev deixa de funcionar.')
    await dialog.getByRole('button', { name: 'Cancelar convite' }).click()

    await expect(page.getByText('Convite para thiago@comunidade.dev cancelado.')).toBeVisible()
    await expect(page.getByRole('row', { name: /Thiago Braga/ })).toHaveCount(0)
  })

  test('troca o papel e remove o acesso com confirmação', async ({ page, goto }) => {
    const team = { members: [me, joao], invitations: [] }
    await openTeam(page, goto, team, {
      'PATCH /members/m-joao': async (route) => {
        expect(route.request().postDataJSON()).toEqual({ role: 'checkin' })
        team.members = [me, { ...joao, role: 'checkin', role_label: 'Credenciamento', access: 'Check-in' }]
        await route.fulfill({ status: 200, json: { data: team.members[1] } })
      },
      'DELETE /members/m-joao': async (route) => {
        team.members = [me]
        await route.fulfill({ status: 204 })
      },
    })

    await page.getByRole('button', { name: 'Editar João Paulo' }).click()
    await page.getByRole('dialog', { name: 'Editar João Paulo' }).getByLabel('Papel').selectOption('checkin')
    await page.getByRole('dialog', { name: 'Editar João Paulo' }).getByRole('button', { name: 'Salvar papel' }).click()
    await expect(page.getByText('Papel de João Paulo alterado para Credenciamento.')).toBeVisible()
    await expect(page.getByRole('row', { name: /João Paulo.*Credenciamento.*Check-in/ })).toBeVisible()

    await page.getByRole('button', { name: 'Editar João Paulo' }).click()
    await page.getByRole('dialog', { name: 'Editar João Paulo' }).getByRole('button', { name: 'Remover acesso' }).click()
    const confirm = page.getByRole('dialog', { name: 'Remover João Paulo da equipe?' })
    await expect(confirm).toContainText('João Paulo perde o acesso a esta edição na hora.')
    await confirm.getByRole('button', { name: 'Remover acesso' }).click()

    await expect(page.getByText('João Paulo não faz mais parte da equipe.')).toBeVisible()
    await expect(page.getByRole('row', { name: /João Paulo/ })).toHaveCount(0)
  })

  test('não deixa remover nem rebaixar o último organizador e explica por quê', async ({ page, goto }) => {
    await openTeam(page, goto, { members: [me, joao], invitations: [] })

    await page.getByRole('button', { name: 'Editar Nome Sobrenome' }).click()
    const dialog = page.getByRole('dialog', { name: 'Editar Nome Sobrenome' })
    await expect(dialog.getByText('A edição precisa de pelo menos uma pessoa organizadora.')).toBeVisible()
    await expect(dialog.getByRole('button', { name: 'Remover acesso' })).toBeDisabled()

    await dialog.getByLabel('Papel').selectOption('avaliador')
    await expect(dialog.getByRole('button', { name: 'Salvar papel' })).toBeDisabled()
    await expect(dialog.getByRole('alert')).toContainText('Promova outra pessoa antes')
  })

  test('mostra a recusa do backend ao remover o último organizador', async ({ page, goto }) => {
    const other = { ...joao, role: 'organizador', role_label: 'Organizador', access: 'Tudo na edição' }
    await openTeam(page, goto, { members: [me, other], invitations: [] }, {
      'DELETE /members/m-joao': (route) => route.fulfill({ status: 422, json: { code: 'validation_failed', message: 'x', errors: { role: ['A edição precisa de pelo menos uma pessoa organizadora.'] }, request_id: 'r' } }),
    })

    await page.getByRole('button', { name: 'Editar João Paulo' }).click()
    await page.getByRole('dialog', { name: 'Editar João Paulo' }).getByRole('button', { name: 'Remover acesso' }).click()
    const confirm = page.getByRole('dialog', { name: 'Remover João Paulo da equipe?' })
    await confirm.getByRole('button', { name: 'Remover acesso' }).click()

    await expect(confirm.getByRole('alert')).toHaveText('A edição precisa de pelo menos uma pessoa organizadora.')
  })
})
