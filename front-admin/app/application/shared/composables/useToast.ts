import { useToastStore, type ToastAction } from '@/stores/toastStore'

/** API enxuta para disparar avisos de qualquer módulo. */
export function useToast() {
  const store = useToastStore()

  return {
    success: (message: string, action?: ToastAction) => store.push('success', message, action),
    info: (message: string) => store.push('info', message),
    warning: (message: string, action?: ToastAction) => store.push('warning', message, action),
    error: (message: string) => store.push('error', message),
    dismiss: (id: number) => store.dismiss(id),
  }
}
