import { qrPath } from '@/application/shared/qr'
import type { AccountTicket } from '../types'

const WIDTH = 720
const QR = 480

/** PNG do ingresso (QR, nome, tipo e código) para guardar no celular. */
export function ticketImage(ticket: AccountTicket, editionName: string): string | null {
  if (!ticket.qr) return null
  const canvas = document.createElement('canvas')
  canvas.width = WIDTH
  canvas.height = 900
  const context = canvas.getContext('2d')
  if (!context) return null

  context.fillStyle = '#ffffff'
  context.fillRect(0, 0, canvas.width, canvas.height)
  context.fillStyle = '#000000'
  context.textAlign = 'center'
  context.font = '600 34px system-ui, sans-serif'
  context.fillText(editionName, WIDTH / 2, 70)
  context.font = '400 28px system-ui, sans-serif'
  context.fillText(ticket.holderName, WIDTH / 2, 120)
  if (ticket.typeName) context.fillText(ticket.typeName, WIDTH / 2, 160)

  const qr = qrPath(ticket.qr)
  const cell = Math.floor(QR / qr.size)
  const offset = (WIDTH - cell * qr.size) / 2
  qr.data.forEach((row, y) => row.forEach((dark, x) => {
    if (dark) context.fillRect(offset + x * cell, 200 + y * cell, cell, cell)
  }))

  context.font = '500 30px ui-monospace, monospace'
  context.fillText(ticket.code, WIDTH / 2, 200 + cell * qr.size + 60)
  return canvas.toDataURL('image/png')
}

export function downloadTicket(ticket: AccountTicket, editionName: string) {
  const url = ticketImage(ticket, editionName)
  if (!url) return
  const link = document.createElement('a')
  link.href = url
  link.download = `ingresso-${ticket.code}.png`
  document.body.appendChild(link)
  link.click()
  link.remove()
}
