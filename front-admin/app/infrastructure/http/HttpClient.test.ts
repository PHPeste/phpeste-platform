import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from './HttpClient'

describe('httpClient', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    httpClient.setAuthToken(null)
    httpClient.setUnauthorizedHandler(null)
  })

  it('appends a query string when query params are given', async () => {
    vi.mocked(fetch).mockResolvedValueOnce(new Response(JSON.stringify({ ok: true }), { status: 200 }))

    await httpClient.get('/items', { query: { scope: 'active', page: 2 } })

    const calledUrl = vi.mocked(fetch).mock.calls[0]?.[0]
    expect(calledUrl).toBe('http://localhost:8000/api/v1/items?scope=active&page=2')
  })

  it('does not append a "?" when no query option is given', async () => {
    vi.mocked(fetch).mockResolvedValueOnce(new Response(JSON.stringify({ ok: true }), { status: 200 }))

    await httpClient.get('/profile')

    const calledUrl = vi.mocked(fetch).mock.calls[0]?.[0]
    expect(calledUrl).toBe('http://localhost:8000/api/v1/profile')
  })

  it('omits query entries whose value is undefined', async () => {
    vi.mocked(fetch).mockResolvedValueOnce(new Response(JSON.stringify({ ok: true }), { status: 200 }))

    await httpClient.get('/items', { query: { status: 'open', label: undefined } })

    const calledUrl = vi.mocked(fetch).mock.calls[0]?.[0]
    expect(calledUrl).toBe('http://localhost:8000/api/v1/items?status=open')
  })

  it('serializes array query values as repeated key[] entries', async () => {
    vi.mocked(fetch).mockResolvedValueOnce(new Response(JSON.stringify({ ok: true }), { status: 200 }))

    await httpClient.get('/items', { query: { tags: [1, 2], year: 2025 } })

    const calledUrl = vi.mocked(fetch).mock.calls[0]?.[0]
    expect(calledUrl).toBe('http://localhost:8000/api/v1/items?tags%5B%5D=1&tags%5B%5D=2&year=2025')
  })

  it('sends a FormData body as-is, without a Content-Type header', async () => {
    vi.mocked(fetch).mockResolvedValueOnce(new Response(JSON.stringify({ ok: true }), { status: 200 }))

    const body = new FormData()
    body.append('avatar', new File(['x'], 'avatar.png', { type: 'image/png' }))
    await httpClient.post('/profile/avatar', { body })

    const options = vi.mocked(fetch).mock.calls[0]?.[1] as RequestInit
    expect(options.body).toBe(body)
    expect((options.headers as Record<string, string>)['Content-Type']).toBeUndefined()
  })

  it('sends a PUT request with a JSON body', async () => {
    vi.mocked(fetch).mockResolvedValueOnce(new Response(JSON.stringify({ ok: true }), { status: 200 }))

    await httpClient.put('/items/1', { body: { status: 'confirmed' } })

    const [calledUrl, options] = vi.mocked(fetch).mock.calls[0]! as [string, RequestInit]
    expect(calledUrl).toBe('http://localhost:8000/api/v1/items/1')
    expect(options.method).toBe('PUT')
    expect(options.body).toBe(JSON.stringify({ status: 'confirmed' }))
  })

  it('throws HttpClientError with the status and message from a non-2xx response', async () => {
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({ message: 'Unauthenticated.' }), { status: 401 }),
    )

    await expect(httpClient.get('/profile')).rejects.toMatchObject({
      statusCode: 401,
      message: 'Unauthenticated.',
    })
    await expect(httpClient.get('/profile')).rejects.toBeInstanceOf(HttpClientError)
  })

  it('reads code and request_id from the API error envelope', async () => {
    vi.mocked(fetch).mockResolvedValueOnce(
      new Response(
        JSON.stringify({ code: 'not_found', message: 'Recurso não encontrado.', errors: null, request_id: 'req-1' }),
        { status: 404 },
      ),
    )

    await expect(httpClient.get('/editions/404')).rejects.toMatchObject({
      code: 'not_found',
      requestId: 'req-1',
    })
  })

  it('falls back to the X-Request-Id header when the body has none', async () => {
    vi.mocked(fetch).mockResolvedValueOnce(
      new Response(JSON.stringify({ message: 'Erro interno.' }), {
        status: 500,
        headers: { 'X-Request-Id': 'from-header' },
      }),
    )

    await expect(httpClient.get('/boom')).rejects.toMatchObject({ requestId: 'from-header' })
  })

  it('calls the unauthorized handler on 401 when a token was set', async () => {
    const onUnauthorized = vi.fn()
    httpClient.setUnauthorizedHandler(onUnauthorized)
    httpClient.setAuthToken('expired-token')
    vi.mocked(fetch).mockResolvedValueOnce(new Response(JSON.stringify({ message: 'Unauthenticated.' }), { status: 401 }))

    await expect(httpClient.get('/profile')).rejects.toBeInstanceOf(HttpClientError)

    expect(onUnauthorized).toHaveBeenCalledOnce()
  })

  it('does not call the unauthorized handler on 401 without a token (e.g. failed login)', async () => {
    const onUnauthorized = vi.fn()
    httpClient.setUnauthorizedHandler(onUnauthorized)
    vi.mocked(fetch).mockResolvedValueOnce(new Response(JSON.stringify({ message: 'Invalid credentials.' }), { status: 401 }))

    await expect(httpClient.post('/auth/login', { body: { email: 'x', password: 'y' } })).rejects.toBeInstanceOf(HttpClientError)

    expect(onUnauthorized).not.toHaveBeenCalled()
  })
})
