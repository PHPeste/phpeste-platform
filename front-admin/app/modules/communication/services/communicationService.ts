import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { CommunicationError } from './communicationError'

export interface TemplatePayload {
  id: string
  label: string
  trigger: string
  transactional: boolean
  variables: string[]
  action_label: string
  subject: string
  body: string
  customized: boolean
  last_sent_at: string | null
}

export interface PreviewPayload { subject: string, html: string, text: string }

export interface DeliveryPayload { id: string, to_email: string, status: string, status_label: string }

export interface ContentBody { subject: string, body: string }

const base = (editionId: string) => `/editions/${editionId}/emails/templates`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new CommunicationError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new CommunicationError('Request failed')
  }
}

export default {
  list: (editionId: string) => call(() => httpClient.get<{ data: TemplatePayload[] }>(base(editionId))),
  save: (editionId: string, templateId: string, body: ContentBody) => call(() => httpClient.put<{ data: TemplatePayload }>(`${base(editionId)}/${templateId}`, { body })),
  restore: (editionId: string, templateId: string) => call(() => httpClient.delete<{ data: TemplatePayload }>(`${base(editionId)}/${templateId}`)),
  preview: (editionId: string, templateId: string, body: ContentBody) => call(() => httpClient.post<{ data: PreviewPayload }>(`${base(editionId)}/${templateId}/preview`, { body })),
  sendTest: (editionId: string, templateId: string, body: ContentBody) => call(() => httpClient.post<{ data: DeliveryPayload }>(`${base(editionId)}/${templateId}/test`, { body })),
}
