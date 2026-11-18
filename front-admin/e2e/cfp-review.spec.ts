import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Nome Sobrenome', email: 'n@s.dev', avatarUrl: null }, token: 'e2e-token' }
const edition = (role: string) => ({
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHPeste', slug: 'phpeste' },
  status: 'cfp_open', status_label: 'CFP aberto', starts_on: null, ends_on: null, city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role,
})
const criteria = [{ id: 'c-1', name: 'Relevância para a comunidade', description: 'O tema interessa?', weight: 3 }, { id: 'c-2', name: 'Clareza da proposta', description: null, weight: 2 }]
const proposal = (number: number, title: string, extra: Record<string, unknown> = {}) => ({
  id: `p-${number}`, number, title, track: { id: 't', name: 'Backend e arquitetura' }, summary: `Resumo da ${title}`, audience: 'Quem usa Laravel.',
  format: 'talk', format_label: 'Palestra de 30 min', duration_minutes: 30, level: 'intermediate', level_label: 'Intermediário', language: 'pt_BR',
  nordeste: number % 2 === 0, speakers_hidden: true, ...extra,
})

async function signIn(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, role: string, routes: (route: Route, method: string, path: string) => Promise<void> | void) {
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition(role)] } }))
  await page.route('**/api/v1/editions/ed-27/overview', (route) => route.abort())
  await page.route('**/api/v1/editions/ed-27/cfp/**', (route) => routes(route, route.request().method(), new URL(route.request().url()).pathname.replace('/api/v1/editions/ed-27/cfp', '')))
  await goto('/cfp/avaliacao', { waitUntil: 'hydration' })
  await expect(page.getByRole('heading', { name: 'Avaliação de propostas', level: 1 })).toBeVisible()
}

test.describe('Avaliação de propostas — revisor', () => {
  const titles = ['Octane em produção', 'Filas e idempotência', 'Testes de mutação']

  function reviewer() {
    const status: Record<string, 'pending' | 'submitted'> = { 'p-1': 'pending', 'p-2': 'pending', 'p-3': 'pending' }
    const bodies: Array<{ id: string, body: unknown }> = []
    const handler = async (route: Route, method: string, path: string) => {
      if (path === '/reviews/queue') {
        return route.fulfill({ status: 200, json: { data: titles.map((title, index) => ({ ...proposal(index + 1, title), my_review_status: status[`p-${index + 1}`] })) } })
      }
      const match = path.match(/^\/proposals\/(p-\d)\/review$/)
      if (match && method === 'GET') {
        const number = Number(match[1]!.slice(2))
        const review = status[match[1]!] === 'submitted' ? { status: 'submitted', scores: { 'c-1': 4, 'c-2': 5 }, comment: null, weighted_score: 4.4, submitted_at: null } : null
        return route.fulfill({ status: 200, json: { data: { proposal: proposal(number, titles[number - 1]!), criteria, review } } })
      }
      if (match && method === 'PUT') {
        const body = route.request().postDataJSON()
        bodies.push({ id: match[1]!, body })
        if (body.submit) status[match[1]!] = 'submitted'
        return route.fulfill({ status: 200, json: { data: { status: body.submit ? 'submitted' : 'draft', scores: body.scores, comment: body.comment, weighted_score: 4.4, submitted_at: null } } })
      }
      if (path.endsWith('/reviews')) return route.fulfill({ status: 200, json: { data: [{ reviewer: 'Você', status: 'submitted', scores: { 'c-1': 4, 'c-2': 5 }, comment: null, weighted_score: 4.4, submitted_at: null }] } })
      return route.fulfill({ status: 404, json: {} })
    }
    return { handler, bodies }
  }

  test('avalia as propostas da fila em sequência só com o teclado e sem ver o autor', async ({ page, goto }) => {
    const { handler, bodies } = reviewer()
    await signIn(page, goto, 'avaliador', handler)

    await expect(page.getByRole('heading', { name: 'Octane em produção', level: 2 })).toBeVisible()
    await expect(page.getByText('Palestrante oculto')).toBeVisible()
    await expect(page.getByText('Atalhos de teclado')).toBeVisible()
    await expect(page.getByRole('region', { name: 'Resumo da avaliação' })).toHaveCount(0)

    for (const [index, title] of titles.entries()) {
      await expect(page.getByRole('heading', { name: title, level: 2 })).toBeVisible()
      await page.keyboard.press(String(4 - (index % 2)))
      await page.keyboard.press('5')
      await page.keyboard.press('Enter')
      await expect(page.getByText(`Nota enviada para a proposta #${index + 1}.`)).toBeVisible()
    }

    expect(bodies).toEqual([
      { id: 'p-1', body: { scores: { 'c-1': 4, 'c-2': 5 }, comment: null, submit: true } },
      { id: 'p-2', body: { scores: { 'c-1': 3, 'c-2': 5 }, comment: null, submit: true } },
      { id: 'p-3', body: { scores: { 'c-1': 4, 'c-2': 5 }, comment: null, submit: true } },
    ])
    await expect(page.getByText('Você já enviou esta avaliação.')).toBeVisible()
    await expect(page.getByRole('button', { name: /Avaliadas 3/ })).toBeVisible()
    await expect(page.getByRole('main')).not.toContainText('Palestrantes:')
  })

  test('chegando pelo menu, o teclado já avalia sem precisar clicar na proposta', async ({ page, goto }) => {
    const { handler, bodies } = reviewer()
    await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
    await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition('avaliador')] } }))
    await page.route('**/api/v1/editions/ed-27/overview', (route) => route.abort())
    await page.route('**/api/v1/editions/ed-27/cfp/**', (route) => handler(route, route.request().method(), new URL(route.request().url()).pathname.replace('/api/v1/editions/ed-27/cfp', '')))
    await goto('/', { waitUntil: 'hydration' })

    await page.getByRole('navigation', { name: 'Principal' }).getByRole('link', { name: 'Avaliar propostas' }).click()
    await expect(page.getByRole('heading', { name: 'Octane em produção', level: 2 })).toBeVisible()
    await page.keyboard.press('4')
    await page.keyboard.press('4')
    await page.keyboard.press('Enter')

    await expect(page.getByText('Nota enviada para a proposta #1.')).toBeVisible()
    expect(bodies).toHaveLength(1)
  })

  test('comentário, rascunho, próxima/anterior e busca pelo teclado', async ({ page, goto }) => {
    const { handler, bodies } = reviewer()
    await signIn(page, goto, 'avaliador', handler)
    await expect(page.getByRole('heading', { name: 'Octane em produção', level: 2 })).toBeVisible()

    await page.keyboard.press('2')
    await page.keyboard.press('c')
    await expect(page.getByLabel('Comentário para a organização')).toBeFocused()
    await page.keyboard.type('Falta detalhar as métricas.')
    await page.keyboard.press('Escape')
    await page.keyboard.press('s')
    await expect(page.getByText('Rascunho salvo.')).toBeVisible()
    expect(bodies[0]).toEqual({ id: 'p-1', body: { scores: { 'c-1': 2 }, comment: 'Falta detalhar as métricas.', submit: false } })

    await page.keyboard.press('Enter')
    await expect(page.getByRole('alert').filter({ hasText: 'Dê nota para todos os critérios antes de enviar.' })).toBeVisible()

    await page.keyboard.press('j')
    await expect(page.getByRole('heading', { name: 'Filas e idempotência', level: 2 })).toBeVisible()
    await page.keyboard.press('k')
    await expect(page.getByRole('heading', { name: 'Octane em produção', level: 2 })).toBeVisible()

    await page.keyboard.press('/')
    await expect(page.getByRole('searchbox', { name: 'Buscar por número, título ou trilha' })).toBeFocused()
    await page.keyboard.type('mutação')
    await expect(page.getByRole('list', { name: 'Fila de propostas' }).getByRole('button')).toHaveCount(1)
  })
})

test.describe('Avaliação de propostas — organização', () => {
  const evaluation = {
    summary: { proposals: 2, reviewed: 1, without_reviewer: 1, average_score: 3.8, nordeste_percent: 58, nordeste_quota_percent: 50 },
    criteria,
    data: [
      { ...proposal(31, 'Testes de mutação', { speakers_hidden: false, speakers: [{ user_id: 'a', name: 'Ana Lima', email: 'ana@x.dev', state: 'PB', is_owner: true }] }), final_score: 4.1, review_status: 'tie', reviews_assigned: 3, reviews_submitted: 3, criterion_averages: { 'c-1': 4.33, 'c-2': 3.67 } },
      { ...proposal(32, 'Octane em produção', { speakers_hidden: false, speakers: [{ user_id: 'b', name: 'Bruno Melo', email: 'b@x.dev', state: 'SP', is_owner: true }] }), final_score: null, review_status: 'pending', reviews_assigned: 0, reviews_submitted: 0, criterion_averages: {} },
    ],
  }

  test('vê o quadro, atribui revisor, distribui automaticamente e exporta as notas', async ({ page, goto }) => {
    const assigned: string[] = []
    let auto: unknown = null
    await signIn(page, goto, 'organizador', async (route, method, path) => {
      if (path === '/evaluation') return route.fulfill({ status: 200, json: evaluation })
      if (path === '/reviewers') return route.fulfill({ status: 200, json: { data: [{ id: 'r-1', name: 'João Paulo', assigned: 2, submitted: 1 }, { id: 'r-2', name: 'Lívia Ferraz', assigned: 0, submitted: 0 }] } })
      if (path === '/evaluation/export') return route.fulfill({ status: 200, body: '﻿Número,Título\n31,Testes de mutação\n', headers: { 'Content-Type': 'text/csv; charset=UTF-8' } })
      if (path === '/assignments/auto') {
        auto = route.request().postDataJSON()
        return route.fulfill({ status: 200, json: { data: { created: 4, unfilled: [32] } } })
      }
      const match = path.match(/^\/proposals\/(p-\d+)\/(review|reviews|assignments)(?:\/(.+))?$/)
      if (match?.[2] === 'review') {
        const item = evaluation.data.find((row) => row.id === match[1])!
        return route.fulfill({ status: 200, json: { data: { proposal: item, criteria, review: null } } })
      }
      if (match?.[2] === 'reviews') return route.fulfill({ status: 200, json: { data: match[1] === 'p-31' ? [{ reviewer: 'João Paulo', status: 'submitted', scores: { 'c-1': 5, 'c-2': 4 }, comment: 'Ótima.', weighted_score: 4.6, submitted_at: null }] : [] } })
      if (match?.[2] === 'assignments' && method === 'POST') {
        assigned.push(route.request().postDataJSON().reviewer_id)
        return route.fulfill({ status: 201, json: { data: { reviewer_id: 'r-2' } } })
      }
      if (match?.[2] === 'assignments') return route.fulfill({ status: 200, json: { data: assigned.map((id) => ({ reviewer_id: id, name: 'Lívia Ferraz' })) } })
      return route.fulfill({ status: 404, json: {} })
    })

    const kpis = page.getByRole('region', { name: 'Resumo da avaliação' })
    await expect(kpis).toContainText('Sem revisor')
    await expect(kpis).toContainText('3,8')
    await expect(kpis).toContainText('58%')
    await expect(kpis).toContainText('meta: 50%')

    await expect(page.getByRole('heading', { name: 'Octane em produção', level: 2 })).toBeVisible()
    await expect(page.getByText('Palestrantes: Bruno Melo')).toBeVisible()
    await expect(page.getByText('Ninguém atribuído ainda.')).toBeVisible()
    await page.getByLabel('Atribuir revisor', { exact: true }).selectOption({ label: 'Lívia Ferraz · 0 atribuídas' })
    await page.getByRole('button', { name: 'Atribuir', exact: true }).click()
    await expect(page.getByText('Lívia Ferraz vai revisar a proposta #32.')).toBeVisible()
    expect(assigned).toEqual(['r-2'])

    await page.getByRole('button', { name: /Empate 1/ }).click()
    await page.getByRole('list', { name: 'Fila de propostas' }).getByRole('button', { name: /Testes de mutação/ }).click()
    await expect(page.getByRole('list', { name: 'Fila de propostas' })).toContainText('Nota 4,1 · 3 revisões')
    await expect(page.getByText('Ótima.')).toBeVisible()
    await expect(page.getByText('Relevância para a comunidade').last()).toBeVisible()

    await page.getByRole('button', { name: 'Atribuir revisores' }).click()
    const dialog = page.getByRole('dialog', { name: 'Atribuir revisores automaticamente' })
    await dialog.getByLabel('Revisores por proposta').fill('2')
    await dialog.getByRole('button', { name: 'Distribuir' }).click()
    await expect(page.getByText('4 atribuições criadas.')).toBeVisible()
    await expect(page.getByText('Faltaram revisores para as propostas #32.')).toBeVisible()
    expect(auto).toEqual({ reviewers_per_proposal: 2 })

    const download = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Exportar notas' }).click()
    expect((await download).suggestedFilename()).toBe('notas-cfp.csv')
    await expect(page.getByText('Planilha de notas baixada.')).toBeVisible()
  })
})
