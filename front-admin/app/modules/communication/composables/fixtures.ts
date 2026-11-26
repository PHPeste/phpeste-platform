import type { TemplatePayload } from '../services/communicationService'

export const templatePayload = (id: string, label: string, extra: Partial<TemplatePayload> = {}): TemplatePayload => ({
  id,
  label,
  trigger: 'Ao pagar o pedido',
  transactional: true,
  variables: ['nome', 'edicao', 'pedido', 'data'],
  action_label: 'Ver meu ingresso',
  subject: 'Seu ingresso para o {edicao}',
  body: 'Oi, {nome}!\n\nPedido {pedido}.',
  customized: false,
  last_sent_at: null,
  ...extra,
})

export const previewPayload = (subject = 'Seu ingresso para o PHPeste 2027') => ({ data: { subject, html: `<html><body><h1>${subject}</h1></body></html>`, text: subject } })
