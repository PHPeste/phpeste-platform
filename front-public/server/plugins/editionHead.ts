import { fontHref, safeUrl, themeCss, type ResolvedEdition } from '#shared/edition'

const escapeJson = (value: unknown) => JSON.stringify(value).replace(/</g, '\\u003c').replace(/>/g, '\\u003e').replace(/&/g, '\\u0026')
const escapeAttr = (value: string) => value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;')

export default defineNitroPlugin((nitro) => {
  nitro.hooks.hook('render:html', (html, { event }) => {
    const edition = (event.context.edition ?? null) as ResolvedEdition | null
    const font = fontHref(edition?.theme ?? null)
    const favicon = safeUrl(edition?.theme?.logos.favicon)
    const css = themeCss(edition?.theme ?? null)

    if (css) html.head.push(`<style id="edition-theme">${css}</style>`)
    if (font) html.head.push(`<link rel="stylesheet" href="${escapeAttr(font)}">`)
    if (favicon) html.head.push(`<link rel="icon" href="${escapeAttr(favicon)}">`)
    html.head.push(`<script id="edition-data" type="application/json">${escapeJson({ edition, unavailable: !!event.context.editionUnavailable })}</script>`)
  })
})
