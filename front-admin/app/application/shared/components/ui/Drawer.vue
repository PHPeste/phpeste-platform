<script setup lang="ts">
import { ref, useId } from 'vue'
import { useDialog } from '@/application/shared/composables/useDialog'
import locale from '@/application/locales/pt-br/ui'

defineProps<{ title: string }>()
const open = defineModel<boolean>({ default: false })

const dialog = ref<HTMLDialogElement | null>(null)
const titleId = `ph-${useId()}-title`
useDialog(dialog, open)
</script>

<template>
  <dialog
    ref="dialog"
    class="modal modal-end"
    :aria-labelledby="titleId"
    @close="open = false"
    @click.self="open = false"
  >
    <div class="modal-box flex h-full max-h-none w-full max-w-md flex-col gap-4 rounded-none bg-base-100">
      <div class="flex items-start justify-between gap-4">
        <h2 :id="titleId" class="text-lg font-semibold">{{ title }}</h2>
        <button type="button" class="btn btn-ghost btn-square btn-sm" @click="open = false">
          <span aria-hidden="true">✕</span>
          <span class="sr-only">{{ locale.close }}</span>
        </button>
      </div>
      <div class="grow overflow-y-auto text-sm">
        <slot />
      </div>
      <div v-if="$slots.footer" class="flex flex-wrap justify-end gap-2">
        <slot name="footer" />
      </div>
    </div>
  </dialog>
</template>
