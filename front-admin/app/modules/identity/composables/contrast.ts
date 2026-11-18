import type { ContrastPair, ContrastResult, TokenMeta } from '../types'

export const HEX = /^#[0-9a-fA-F]{6}$/

function luminance(hex: string): number {
  const channels = [1, 3, 5].map((index) => Number.parseInt(hex.slice(index, index + 2), 16) / 255)
    .map((value) => (value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4))

  return 0.2126 * (channels[0] ?? 0) + 0.7152 * (channels[1] ?? 0) + 0.0722 * (channels[2] ?? 0)
}

export function contrastRatio(a: string, b: string): number {
  const [lighter, darker] = [luminance(a), luminance(b)].sort((x, y) => y - x) as [number, number]
  return Math.round(((lighter + 0.05) / (darker + 0.05)) * 100) / 100
}

export function evaluateContrast(colors: Record<string, string>, pairs: ContrastPair[], tokens: TokenMeta[]): ContrastResult[] {
  const label = (id: string) => tokens.find((token) => token.id === id)?.label ?? id

  return pairs.flatMap((pair) => {
    const foreground = colors[pair.foreground]
    const background = colors[pair.background]

    if (!foreground || !background || !HEX.test(foreground) || !HEX.test(background)) {
      return []
    }

    const ratio = contrastRatio(foreground, background)
    const passes = ratio >= pair.minimum

    return [{
      ...pair,
      label: `${label(pair.foreground)} sobre ${label(pair.background)}`,
      ratio,
      passes,
      level: !passes ? 'fail' : ratio >= (pair.minimum === 3 ? 4.5 : 7) ? 'AAA' : 'AA',
    } satisfies ContrastResult]
  })
}
