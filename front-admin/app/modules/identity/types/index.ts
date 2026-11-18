export type LogoSlot = 'primary' | 'dark' | 'favicon' | 'app_icon'

export interface Theme {
  colors: Record<string, string>
  fonts: { heading: string, body: string }
  radius: number
  logos: Record<LogoSlot, string | null>
}

export interface ThemeState {
  draft: Theme
  published: Theme | null
  publishedAt: string | null
  hasUnpublishedChanges: boolean
  canRevert: boolean
}

export interface Preset {
  id: string
  label: string
  description: string
  colors: Record<string, string>
}

export interface TokenMeta {
  id: string
  label: string
}

export interface ContrastPair {
  foreground: string
  background: string
  minimum: number
}

export interface ContrastResult extends ContrastPair {
  label: string
  ratio: number
  level: 'AAA' | 'AA' | 'fail'
  passes: boolean
}

export interface EditorMeta {
  tokens: TokenMeta[]
  pairs: ContrastPair[]
  fonts: string[]
  radius: { min: number, max: number }
}
