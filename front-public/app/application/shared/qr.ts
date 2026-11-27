import { encode } from 'uqr'

export interface QrMatrix { size: number, path: string, data: boolean[][] }

export function qrPath(value: string): QrMatrix {
  const qr = encode(value, { ecc: 'M', border: 2 })
  const path = qr.data.flatMap((row, y) => row.map((dark, x) => (dark ? `M${x} ${y}h1v1h-1z` : ''))).join('')
  return { size: qr.size, path, data: qr.data }
}
