<script setup lang="ts">
import locale from '@/application/locales/pt-br/ui'

withDefaults(defineProps<{ title: string, description?: string, retrying?: boolean }>(), { description: undefined, retrying: false })
const emit = defineEmits<{ retry: [] }>()
</script>

<template>
  <div role="alert" class="flex flex-col items-center gap-3 rounded-box border border-(--ph-danger) bg-(--ph-danger-bg) px-4 py-10 text-center">
    <span class="flex size-12 items-center justify-center rounded-full bg-(--ph-danger) text-xl font-bold text-(--ph-on-brand)" aria-hidden="true">!</span>
    <h3 class="text-base font-semibold text-(--ph-ink)">{{ title }}</h3>
    <p class="max-w-sm text-sm text-(--ph-ink-2)">{{ description ?? locale.loadErrorHint }}</p>
    <PhButton variant="secondary" :loading="retrying" @click="emit('retry')">{{ locale.retry }}</PhButton>
  </div>
</template>
