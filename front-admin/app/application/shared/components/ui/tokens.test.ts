import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

// Lê os tokens reais de main.css: se alguém trocar uma cor e quebrar o
// contraste mínimo (WCAG AA = 4,5:1 para texto), este teste falha.
const css = readFileSync(resolve(process.cwd(), 'app/assets/css/main.css'), 'utf8')

function token(name: string): string {
  const match = css.match(new RegExp(`--ph-${name}:\\s*(#[0-9a-fA-F]{6})`))
  if (!match?.[1]) throw new Error(`Token --ph-${name} não encontrado em main.css`)
  return match[1]
}

function luminance(hex: string): number {
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((c) =>
    c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4,
  ) as [number, number, number]
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function ratio(a: string, b: string): number {
  const [hi, lo] = [luminance(token(a)), luminance(token(b))].sort((x, y) => y - x) as [number, number]
  return (hi + 0.05) / (lo + 0.05)
}

const textPairs: Array<[string, string]> = [
  ['ink', 'bg'], ['ink', 'surface'], ['ink', 'surface-2'],
  ['ink-2', 'bg'], ['ink-2', 'surface'], ['ink-2', 'surface-2'],
  ['ink-3', 'bg'], ['ink-3', 'surface'], ['ink-3', 'surface-2'],
  ['link', 'bg'], ['link', 'surface'],
  ['on-brand', 'brand'], ['on-brand', 'terra'],
  ['ink', 'accent'],
  ['ok', 'ok-bg'], ['warn', 'warn-bg'], ['danger', 'danger-bg'], ['info', 'info-bg'],
  ['danger', 'surface'], ['ok', 'surface'], ['warn', 'surface'], ['info', 'surface'],
  ['on-brand', 'ok'], ['on-brand', 'warn'], ['on-brand', 'danger'], ['on-brand', 'info'],
]

describe('contraste dos tokens do tema Sertão', () => {
  it.each(textPairs)('texto --ph-%s sobre --ph-%s tem pelo menos 4,5:1', (fg, bg) => {
    expect(ratio(fg, bg)).toBeGreaterThanOrEqual(4.5)
  })

  it.each([['brand', 'bg'], ['brand', 'surface']])(
    'anel de foco --ph-%s sobre --ph-%s tem pelo menos 3:1',
    (fg, bg) => {
      expect(ratio(fg, bg)).toBeGreaterThanOrEqual(3)
    },
  )
})
