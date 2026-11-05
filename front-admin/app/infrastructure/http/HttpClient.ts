export class HttpClientError extends Error {
  statusCode: number | null
  fieldErrors: Record<string, string[]> | null
  code: string | null
  requestId: string | null

  constructor(
    message: string,
    statusCode: number | null,
    fieldErrors: Record<string, string[]> | null = null,
    code: string | null = null,
    requestId: string | null = null,
  ) {
    super(message)
    this.name = 'HttpClientError'
    this.statusCode = statusCode
    this.fieldErrors = fieldErrors
    this.code = code
    this.requestId = requestId
  }
}

type HttpClientQueryValue = string | number | boolean | Array<string | number> | undefined

interface HttpClientRequestOptions {
  body?: unknown
  headers?: Record<string, string>
  query?: Record<string, HttpClientQueryValue>
}

interface ApiErrorPayload {
  message?: string
  errors?: Record<string, string[]>
  code?: string
  request_id?: string
}

let authToken: string | null = null
let onUnauthorized: (() => void) | null = null

function setAuthToken(token: string | null) {
  authToken = token
}

/**
 * Chamado quando uma requisição que levava token recebe 401 — sessão
 * expirada ou revogada. Quem decide o que fazer (limpar o authStore,
 * navegar pro login) é o plugin de auth, não o HttpClient.
 */
function setUnauthorizedHandler(handler: (() => void) | null) {
  onUnauthorized = handler
}

// Arrays become `key[]=a&key[]=b` — the query string shape Laravel parses
// natively as an array on the request side.
function buildQueryString(query: HttpClientRequestOptions['query']): string {
  if (!query) {
    return ''
  }

  const params = new URLSearchParams()

  for (const [key, value] of Object.entries(query)) {
    if (value === undefined) {
      continue
    }
    if (Array.isArray(value)) {
      for (const item of value) {
        params.append(`${key}[]`, String(item))
      }
      continue
    }
    params.append(key, String(value))
  }

  const serialized = params.toString()
  return serialized ? `?${serialized}` : ''
}

async function request<T>(method: string, path: string, options: HttpClientRequestOptions = {}): Promise<T> {
  const { apiBase } = useRuntimeConfig().public
  const hadToken = authToken !== null
  let response: Response

  // FormData (file upload) can't be JSON.stringify'd, and Content-Type has
  // to stay unset — the browser sets its own multipart boundary, setting it
  // manually breaks parsing on the backend.
  const isFormData = options.body instanceof FormData

  try {
    response = await fetch(`${apiBase}${path}${buildQueryString(options.query)}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
        ...options.headers,
      },
      body: isFormData ? (options.body as FormData) : (options.body ? JSON.stringify(options.body) : undefined),
    })
  } catch {
    throw new HttpClientError('Could not connect to the server. Check your connection.', null)
  }

  const data = response.status === 204 ? null : await response.json().catch(() => null)

  if (!response.ok) {
    const payload = data as ApiErrorPayload | null
    const requestId = payload?.request_id ?? response.headers.get('X-Request-Id')

    if (response.status === 401 && hadToken) {
      onUnauthorized?.()
    }

    throw new HttpClientError(
      payload?.message ?? 'Something went wrong.',
      response.status,
      payload?.errors ?? null,
      payload?.code ?? null,
      requestId,
    )
  }

  return data as T
}

export const httpClient = {
  setAuthToken,
  setUnauthorizedHandler,
  get: <T>(path: string, options?: HttpClientRequestOptions) => request<T>('GET', path, options),
  post: <T>(path: string, options?: HttpClientRequestOptions) => request<T>('POST', path, options),
  put: <T>(path: string, options?: HttpClientRequestOptions) => request<T>('PUT', path, options),
  patch: <T>(path: string, options?: HttpClientRequestOptions) => request<T>('PATCH', path, options),
  delete: <T>(path: string, options?: HttpClientRequestOptions) => request<T>('DELETE', path, options),
}

export default httpClient
