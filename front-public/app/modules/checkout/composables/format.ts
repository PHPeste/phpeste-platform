const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })

/** 15000 → "R$ 150,00". */
export const formatCents = (cents: number) => brl.format(cents / 100)

const dateTime = new Intl.DateTimeFormat('pt-BR', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })

/** ISO → "10 de junho, 09:00". */
export const formatDateTime = (iso: string) => dateTime.format(new Date(iso))

const clock = new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' })

export const formatClock = (iso: string) => clock.format(new Date(iso))

/** Segundos → "14:05". */
export function formatCountdown(seconds: number): string {
  const safe = Math.max(0, Math.floor(seconds))
  return `${String(Math.floor(safe / 60)).padStart(2, '0')}:${String(safe % 60).padStart(2, '0')}`
}

export const digits = (text: string) => text.replace(/\D/g, '')

/** CPF com os dois dígitos verificadores certos. */
export function isCpf(text: string): boolean {
  const value = digits(text)
  if (value.length !== 11 || /^(\d)\1{10}$/.test(value)) return false
  const check = (length: number) => {
    const sum = [...value.slice(0, length)].reduce((total, digit, index) => total + Number(digit) * (length + 1 - index), 0)
    const rest = (sum * 10) % 11
    return rest === 10 ? 0 : rest
  }
  return check(9) === Number(value[9]) && check(10) === Number(value[10])
}

/** "12345678909" → "123.456.789-09", enquanto a pessoa digita. */
export function maskCpf(text: string): string {
  const value = digits(text).slice(0, 11)
  return value.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2')
}

export const isEmail = (text: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text.trim())
