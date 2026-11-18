import { getRequestHost, sendRedirect, setResponseStatus } from 'h3'
import { normalizeHost, route } from '#shared/edition'

const SKIP = /^\/(_nuxt|__nuxt|api|_ipx|favicon\.ico|robots\.txt)/

export default defineEventHandler(async (event) => {
  if (SKIP.test(event.path)) return

  const { trustForwardedHost } = useRuntimeConfig(event)
  const host = normalizeHost(getRequestHost(event, { xForwardedHost: trustForwardedHost }))

  let edition = null
  try {
    edition = host ? await resolveEdition(event, host) : null
  } catch {
    event.context.editionUnavailable = true
  }

  event.context.edition = edition

  if (!edition) {
    setResponseStatus(event, event.context.editionUnavailable ? 503 : 404)
    return
  }

  const decision = route(edition, event.path)
  if (decision.kind === 'redirect') return sendRedirect(event, decision.location, decision.status)
})
