import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page } from '@playwright/test'

const cfp = (status = 'open') => ({ data: {
  status, status_label: status === 'open' ? 'CFP aberto' : 'CFP encerrado', opens_at: '2027-03-01T03:00:00Z', closes_at: '2027-04-16T02:59:59Z', anonymous_review: true, proposals_per_person: 2,
  tracks: [{ id: 't-1', name: 'Backend e arquitetura' }, { id: 't-2', name: 'Carreira e comunidade' }],
  form: { formats: [{ id: 'talk', label: 'Palestra de 30 min' }, { id: 'workshop', label: 'Oficina de 2 h' }], levels: [{ id: 'beginner', label: 'Iniciante' }, { id: 'intermediate', label: 'Intermediário' }], languages: [{ id: 'pt_BR', label: 'Português' }], limits: { title: 90, summary: 3000, audience: 1000 } },
} })

interface Store { proposal: Record<string, unknown> | null, calls: string[], profile: Record<string, unknown> | null }

async function mockApi(page: Page, store: Store, status = 'open') {
  await page.route('**/api/v1/public/editions/ed-a/cfp', (route) => route.fulfill({ json: cfp(status) }))
  await page.route('**/api/v1/auth/register', (route) => {
    store.calls.push('register')
    return route.fulfill({ status: 201, json: { data: { id: 'u-1' } } })
  })
  await page.route('**/api/v1/auth/login', (route) => {
    store.calls.push('login')
    return route.fulfill({ json: { user: { id: 'u-1', name: 'Ana Martins', email: 'ana@x.dev', avatar_url: null, created_at: '2027-01-01' }, token: 'tok' } })
  })
  await page.route('**/api/v1/me/speaker-profile', async (route) => {
    if (route.request().method() === 'PUT') {
      store.profile = route.request().postDataJSON()
      store.calls.push('profile')
    }
    return route.fulfill({ json: { data: { public: store.profile ? { ...store.profile, links: { github: null, linkedin: null, website: null } } : null, private: { accessibility_needs: null, dietary_restrictions: null }, email: 'ana@x.dev' } } })
  })
  await page.route('**/api/v1/speaker/editions/ed-a/proposals**', async (route) => {
    const method = route.request().method()
    const path = new URL(route.request().url()).pathname.replace('/api/v1/speaker/editions/ed-a/proposals', '')
    const base = { id: 'p-1', number: 7, track: null, summary: null, audience: null, format: null, format_label: null, level: null, level_label: null, language: 'pt_BR', status: 'draft', status_label: 'Rascunho', decision: null, decision_label: null, missing_for_submission: [], speakers: [{ user_id: 'u-1', is_owner: true }], confirmation: null }
    const merge = (body: Record<string, unknown>) => {
      store.proposal = { ...base, ...(store.proposal ?? {}), ...body, track: body.track_id ? { id: body.track_id, name: body.track_id === 't-2' ? 'Carreira e comunidade' : 'Backend e arquitetura' } : (store.proposal?.track ?? null) }
    }
    if (method === 'POST' && path === '') {
      store.calls.push('create')
      merge(route.request().postDataJSON())
      return route.fulfill({ status: 201, json: { data: store.proposal } })
    }
    if (method === 'PATCH') {
      store.calls.push('update')
      merge(route.request().postDataJSON())
      return route.fulfill({ json: { data: store.proposal } })
    }
    if (path === '/p-1/submit') {
      store.calls.push('submit')
      store.proposal = { ...store.proposal, status: 'submitted', status_label: 'Enviada' }
      return route.fulfill({ json: { data: store.proposal } })
    }
    if (path === '/p-1/confirm') {
      store.calls.push('confirm')
      store.proposal = { ...store.proposal, confirmation: { status: 'confirmed', status_label: 'Confirmada', deadline_at: '2027-05-08T23:59:59Z', responded_at: '2027-05-02T12:00:00Z' } }
      return route.fulfill({ json: { data: { status: 'confirmed' } } })
    }
    if (path === '' && method === 'GET') return route.fulfill({ json: { data: store.proposal ? [store.proposal] : [] } })
    if (path === '/p-1') return route.fulfill({ json: { data: store.proposal } })
    return route.fulfill({ status: 404, json: {} })
  })
}

test.describe('Formulário público de proposta', () => {
  test('cria a conta no meio do caminho, o rascunho sobrevive a recarregar e a proposta é enviada', async ({ page, goto }) => {
    const store: Store = { proposal: null, calls: [], profile: null }
    await mockApi(page, store)
    await goto('/cfp', { waitUntil: 'hydration' })

    await expect(page.getByText('Passo 1 de 3 · Sobre você').first()).toBeVisible()
    await expect(page.getByText('A avaliação é anônima: quem revisa não vê o seu nome.')).toBeVisible()
    const auth = page.getByTestId('auth-panel')
    await auth.getByRole('tab', { name: 'Criar conta' }).click()
    await auth.getByLabel(/^Nome/).fill('Ana Martins')
    await auth.getByLabel(/^E-mail/).fill('ana@x.dev')
    await auth.getByLabel(/^Senha/).fill('uma-senha-forte-2027')
    await auth.getByLabel(/^Repetir senha/).fill('uma-senha-forte-2027')
    await auth.getByRole('button', { name: 'Criar conta' }).click()

    await expect(page.getByText('Você entrou como Ana Martins.')).toBeVisible()
    await page.getByLabel(/^Nome para o site/).fill('Ana Martins')
    await page.getByLabel(/^Cidade/).fill('João Pessoa')
    await page.getByLabel(/^Estado/).selectOption('PB')
    await page.getByRole('button', { name: 'Continuar' }).click()

    await expect(page.getByText('Passo 2 de 3 · Sobre a palestra').first()).toBeVisible()
    await page.getByLabel(/^Título da palestra/).fill('Octane em produção: o que aprendemos')
    await page.getByLabel(/^Trilha/).selectOption({ label: 'Backend e arquitetura' })
    await page.getByLabel(/^Formato/).selectOption({ label: 'Palestra de 30 min' })
    await page.getByLabel(/^Nível/).selectOption({ label: 'Intermediário' })
    await page.getByLabel(/^Resumo para a avaliação/).fill('Seis meses rodando Laravel com Octane.')
    await expect(page.getByText(/Rascunho salvo às/)).toBeVisible()

    await page.reload()
    await expect(page.getByLabel(/^Título da palestra/)).toHaveValue('Octane em produção: o que aprendemos')
    await expect(page.getByLabel(/^Resumo para a avaliação/)).toHaveValue('Seis meses rodando Laravel com Octane.')

    await page.getByRole('button', { name: 'Continuar' }).click()
    await expect(page.getByText('Obrigatório.').first()).toBeVisible()
    await page.getByLabel(/^Para quem é essa palestra/).fill('Quem já usa Laravel.')
    await page.getByRole('button', { name: 'Continuar' }).click()

    await expect(page.getByTestId('review-talk')).toContainText('Octane em produção: o que aprendemos')
    await expect(page.getByTestId('review-talk')).toContainText('Backend e arquitetura · Palestra de 30 min · Intermediário · Português')
    await page.getByRole('button', { name: 'Enviar proposta' }).click()

    await expect(page.getByRole('heading', { name: 'Proposta enviada!' })).toBeVisible()
    await expect(page.getByTestId('submitted')).toContainText('dá para editar a proposta até 15 de abril')
    expect(store.calls.filter((call) => call === 'create')).toHaveLength(1)
    expect(store.calls).toEqual(expect.arrayContaining(['register', 'login', 'profile', 'create', 'submit']))
    expect(store.profile).toMatchObject({ display_name: 'Ana Martins', state: 'PB', city: 'João Pessoa' })
    expect(await page.evaluate(() => localStorage.getItem('phpeste.cfp.draft.ed-a'))).toBeNull()
  })

  test('palestrante aprovado confirma a participação em Minhas propostas', async ({ page, goto }) => {
    const store: Store = { calls: [], profile: null, proposal: {
      id: 'p-1', number: 7, title: 'Octane em produção', track: { id: 't-1', name: 'Backend e arquitetura' }, summary: 'Resumo', audience: 'Público', format: 'talk', format_label: 'Palestra de 30 min', level: 'beginner', level_label: 'Iniciante', language: 'pt_BR',
      status: 'submitted', status_label: 'Enviada', decision: 'accepted', decision_label: 'Aprovada', missing_for_submission: [], speakers: [{ user_id: 'u-1', is_owner: true }],
      confirmation: { status: 'pending', status_label: 'Aguardando resposta', deadline_at: '2027-05-08T23:59:59Z', responded_at: null },
    } }
    await mockApi(page, store, 'closed')
    await page.addInitScript(() => localStorage.setItem('app.auth', JSON.stringify({ user: { id: 'u-1', name: 'Ana Martins', email: 'ana@x.dev', avatarUrl: null }, token: 'tok' })))
    await goto('/palestrante/propostas', { waitUntil: 'hydration' })

    const item = page.getByRole('listitem').filter({ hasText: 'Octane em produção' })
    await expect(item).toContainText('Aprovada')
    await expect(item).toContainText('Confirmação: Aguardando resposta')
    await item.getByRole('link', { name: 'Abrir Octane em produção' }).click()

    const confirmation = page.getByTestId('confirmation')
    await expect(confirmation).toContainText('Confirme sua participação até 8 de maio')
    await confirmation.getByRole('button', { name: 'Confirmar participação' }).click()
    await expect(page.getByText('Participação confirmada. Obrigado!')).toBeVisible()
    await expect(confirmation).toContainText('Confirmação: Confirmada')
    expect(store.calls).toContain('confirm')
  })

  test('fora da janela, o formulário aparece como encerrado', async ({ page, goto }) => {
    const store: Store = { proposal: null, calls: [], profile: null }
    await mockApi(page, store, 'closed')
    await goto('/cfp', { waitUntil: 'hydration' })

    await expect(page.getByTestId('cfp-closed')).toContainText('O CFP desta edição está encerrado')
    await expect(page.getByTestId('auth-panel')).toHaveCount(0)
  })

  test('entrando por Minhas propostas, a lista carrega na hora e o perfil salvo volta ao formulário', async ({ page, goto }) => {
    const store: Store = { calls: [], profile: { display_name: 'Ana Salva', state: 'CE', city: 'Fortaleza' }, proposal: {
      id: 'p-1', number: 7, title: 'Octane em produção', track: null, summary: null, audience: null, format: null, format_label: null, level: null, level_label: null, language: 'pt_BR',
      status: 'draft', status_label: 'Rascunho', decision: null, decision_label: null, missing_for_submission: [], speakers: [{ user_id: 'u-1', is_owner: true }], confirmation: null,
    } }
    await mockApi(page, store)
    await goto('/palestrante/propostas', { waitUntil: 'hydration' })

    const auth = page.getByTestId('auth-panel')
    await auth.getByLabel(/^E-mail/).fill('ana@x.dev')
    await auth.getByLabel(/^Senha/).fill('senha-qualquer')
    await auth.getByRole('button', { name: 'Entrar' }).click()
    await expect(page.getByRole('listitem').filter({ hasText: 'Octane em produção' })).toContainText('Rascunho')

    await page.getByRole('link', { name: 'Sair' }).or(page.getByRole('button', { name: 'Sair' })).click()
    await goto('/cfp', { waitUntil: 'hydration' })
    await page.getByTestId('auth-panel').getByLabel(/^E-mail/).fill('ana@x.dev')
    await page.getByTestId('auth-panel').getByLabel(/^Senha/).fill('senha-qualquer')
    await page.getByTestId('auth-panel').getByRole('button', { name: 'Entrar' }).click()
    await expect(page.getByLabel(/^Nome para o site/)).toHaveValue('Ana Salva')
    await expect(page.getByLabel(/^Estado/)).toHaveValue('CE')
  })
})
