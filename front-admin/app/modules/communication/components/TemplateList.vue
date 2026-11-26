<script setup lang="ts">
import { formatLastSent } from '../composables/format'
import locale from '../locales/pt-br'
import type { EmailTemplate } from '../types'

defineProps<{ templates: EmailTemplate[], selectedId: string | null }>()
defineEmits<{ select: [templateId: string] }>()
</script>

<template>
  <section class="card overflow-hidden border border-base-300 bg-base-100" aria-labelledby="templates-title">
    <div class="px-5 pt-4 pb-2">
      <h2 id="templates-title" class="text-lg font-semibold">{{ locale.listTitle }}</h2>
    </div>
    <PhEmptyState v-if="!templates.length" :title="locale.emptyTitle" :description="locale.emptyHint" />
    <PhTable v-else :caption="locale.tableCaption">
      <thead>
        <tr>
          <th scope="col">{{ locale.colTemplate }}</th>
          <th scope="col">{{ locale.colTrigger }}</th>
          <th scope="col">{{ locale.colLastSent }}</th>
          <th scope="col"><span class="sr-only">{{ locale.colActions }}</span></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="template in templates" :key="template.id" :class="{ 'bg-(--ph-surface-2)': template.id === selectedId }" :data-testid="`template-${template.id}`">
          <th scope="row" class="font-medium">
            {{ template.label }}
            <PhBadge v-if="template.customized" status="info" class="ml-1 align-middle">{{ locale.customized }}</PhBadge>
          </th>
          <td class="text-sm">{{ template.trigger }}</td>
          <td class="text-sm whitespace-nowrap tabular-nums">{{ template.lastSentAt ? formatLastSent(template.lastSentAt) : locale.never }}</td>
          <td class="text-right">
            <button
              type="button"
              class="btn btn-ghost btn-sm"
              :aria-label="locale.editLabel.replace('{modelo}', template.label)"
              :aria-current="template.id === selectedId ? 'true' : undefined"
              @click="$emit('select', template.id)"
            >
              {{ template.id === selectedId ? locale.editing : locale.edit }}
            </button>
          </td>
        </tr>
      </tbody>
    </PhTable>
  </section>
</template>
