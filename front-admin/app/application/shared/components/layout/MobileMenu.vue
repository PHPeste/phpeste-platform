<script setup lang="ts">
import { ref } from 'vue'
import { X } from '@lucide/vue'
import { useDialog } from '@/application/shared/composables/useDialog'
import locale from '@/modules/shell/locales/pt-br'

const open = defineModel<boolean>({ default: false })
const dialog = ref<HTMLDialogElement | null>(null)
useDialog(dialog, open)
</script>

<template>
  <dialog
    ref="dialog"
    class="modal modal-start lg:hidden"
    :aria-label="locale.mainNav"
    @close="open = false"
    @click.self="open = false"
  >
    <div class="modal-box relative h-full max-h-none w-[min(18rem,85vw)] max-w-none rounded-none p-0" style="background: var(--ph-ink)">
      <button
        type="button"
        class="btn btn-ghost btn-square absolute right-2 top-2 z-10 text-(--ph-side-text)"
        :aria-label="locale.closeMenu"
        @click="open = false"
      >
        <X class="size-5" aria-hidden="true" />
      </button>
      <SideNav @navigate="open = false" />
    </div>
  </dialog>
</template>
