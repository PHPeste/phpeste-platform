import { onMounted, watch, type Ref } from 'vue'

/**
 * Liga um <dialog> nativo a um `open` reativo. O elemento nativo já cuida de
 * prender o foco, fechar com Esc e devolver o foco a quem o abriu.
 * O fallback com atributo `open` existe só para ambientes sem showModal (testes).
 */
export function useDialog(el: Ref<HTMLDialogElement | null>, open: Ref<boolean>) {
  function sync() {
    const dialog = el.value
    if (!dialog) return
    if (open.value && !dialog.open) {
      if (typeof dialog.showModal === 'function') dialog.showModal()
      else dialog.setAttribute('open', '')
    } else if (!open.value && dialog.open) {
      if (typeof dialog.close === 'function') dialog.close()
      else dialog.removeAttribute('open')
    }
  }

  onMounted(sync)
  watch(open, sync, { flush: 'post' })
}
