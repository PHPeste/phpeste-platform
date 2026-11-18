<script setup lang="ts">
import { useToast } from '@/application/shared/composables/useToast'
import locale from '../locales/pt-br'
import type { NotificationOption, Result } from '../types'

const props = defineProps<{ options: NotificationOption[], busy: string | null, toggle: (id: string, enabled: boolean) => Promise<Result> }>()
const toast = useToast()

async function change(option: NotificationOption, enabled: boolean) {
  const result = await props.toggle(option.id, enabled)
  if (result.ok) toast.success((enabled ? locale.notificationOn : locale.notificationOff).replace('{nome}', option.label))
  else toast.error(result.message)
}
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-1">
        <h2 class="text-lg font-semibold">{{ locale.notificationsTitle }}</h2>
        <p class="text-sm text-(--ph-ink-2)">{{ locale.notificationsHint }}</p>
      </div>
      <ul class="flex flex-col">
        <li v-for="option in options" :key="option.id" class="flex items-center justify-between gap-4 border-t border-(--ph-line) py-3 first:border-t-0">
          <label :for="`notification-${option.id}`" class="flex min-w-0 cursor-pointer flex-col">
            <span class="font-medium">{{ option.label }}</span>
            <span class="text-sm text-(--ph-ink-2)">{{ option.description }}</span>
          </label>
          <input
            :id="`notification-${option.id}`"
            type="checkbox"
            class="toggle toggle-primary shrink-0"
            :checked="option.enabled"
            :disabled="busy === `notification:${option.id}`"
            @change="change(option, ($event.target as HTMLInputElement).checked)"
          >
        </li>
      </ul>
    </div>
  </PhCard>
</template>
