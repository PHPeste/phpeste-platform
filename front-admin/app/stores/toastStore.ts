import { defineStore } from 'pinia'

export type ToastKind = 'success' | 'info' | 'warning' | 'error'

export interface ToastAction {
  label: string
  run: () => void
}

export interface Toast {
  id: number
  kind: ToastKind
  message: string
  action?: ToastAction
}

interface ToastState {
  items: Toast[]
  nextId: number
}

// Avisos precisam de tempo para serem lidos; com ação (ex.: "Desfazer"), mais.
const DURATION_MS: Record<ToastKind, number> = {
  success: 5000,
  info: 5000,
  warning: 8000,
  error: 10000,
}
const DURATION_WITH_ACTION_MS = 10000

/**
 * Store global legítima: qualquer módulo dispara um aviso e um único
 * <PhToastHost> (no layout) os mostra. Ver docs/ARCHITECTURE.md, "Estado global".
 */
export const useToastStore = defineStore('toast', {
  state: (): ToastState => ({ items: [], nextId: 1 }),

  actions: {
    push(kind: ToastKind, message: string, action?: ToastAction): number {
      const id = this.nextId++
      this.items.push({ id, kind, message, action })
      if (import.meta.client) {
        setTimeout(() => this.dismiss(id), action ? DURATION_WITH_ACTION_MS : DURATION_MS[kind])
      }
      return id
    },

    dismiss(id: number) {
      this.items = this.items.filter((toast) => toast.id !== id)
    },
  },
})
