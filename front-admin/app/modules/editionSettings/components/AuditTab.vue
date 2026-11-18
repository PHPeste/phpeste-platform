<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useAuditLog } from '../composables/useAuditLog'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string }>()
const audit = useAuditLog(props.editionId)
const { entries, people, page, lastPage, state, filters, hasFilters } = audit

onMounted(() => audit.load(1))

const actionOptions = [{ value: '', label: locale.allActions }, ...Object.entries(locale.actions).map(([value, label]) => ({ value, label }))]
const peopleOptions = computed(() => [{ value: '', label: locale.allPeople }, ...people.value.map((person) => ({ value: person.id, label: person.name }))])
const when = (iso: string) => new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-1">
        <h2 class="text-lg font-semibold">{{ locale.auditTitle }}</h2>
        <p class="text-sm text-(--ph-ink-2)">{{ locale.auditHint }}</p>
      </div>
      <form role="search" :aria-label="locale.filters" class="grid items-end gap-3 md:grid-cols-[1.4fr_1fr_auto_auto_auto]" @submit.prevent="audit.load(1)">
        <PhSelect v-model="filters.action" :label="locale.action" :options="actionOptions" @update:model-value="audit.load(1)" />
        <PhSelect v-model="filters.actorId" :label="locale.person" :options="peopleOptions" @update:model-value="audit.load(1)" />
        <PhInput v-model="filters.from" type="date" :label="locale.from" @change="audit.load(1)" />
        <PhInput v-model="filters.to" type="date" :label="locale.to" @change="audit.load(1)" />
        <PhButton v-if="hasFilters" variant="ghost" @click="audit.clear">{{ locale.clear }}</PhButton>
      </form>

      <PhPageSkeleton v-if="state === 'loading' && !entries.length" :cards="1" :lines="6" />
      <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="audit.load(page)" />
      <PhEmptyState v-else-if="!entries.length && hasFilters" :title="locale.auditEmptyFilter">
        <PhButton variant="secondary" @click="audit.clear">{{ locale.clear }}</PhButton>
      </PhEmptyState>
      <PhEmptyState v-else-if="!entries.length" :title="locale.auditEmpty" :description="locale.auditEmptyHint">
        <PhButton variant="secondary" to="/equipe">{{ locale.auditEmptyAction }}</PhButton>
      </PhEmptyState>
      <template v-else>
        <PhTable :caption="locale.auditCaption">
          <thead>
            <tr>
              <th scope="col">{{ locale.when }}</th>
              <th scope="col">{{ locale.who }}</th>
              <th scope="col">{{ locale.what }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in entries" :key="entry.id">
              <td class="whitespace-nowrap tabular-nums">{{ when(entry.createdAt) }}</td>
              <td>{{ entry.actor?.name ?? locale.system }}</td>
              <td>
                <span class="flex flex-col">
                  <span>{{ entry.actionLabel }}</span>
                  <span v-if="entry.detail" class="text-xs text-(--ph-ink-3)">{{ entry.detail }}</span>
                </span>
              </td>
            </tr>
          </tbody>
        </PhTable>
        <nav v-if="lastPage > 1" class="flex items-center justify-between gap-3 text-sm" :aria-label="locale.auditCaption">
          <PhButton variant="ghost" :disabled="page <= 1" @click="audit.load(page - 1)">{{ locale.previous }}</PhButton>
          <span>{{ locale.page.replace('{n}', String(page)).replace('{total}', String(lastPage)) }}</span>
          <PhButton variant="ghost" :disabled="page >= lastPage" @click="audit.load(page + 1)">{{ locale.next }}</PhButton>
        </nav>
      </template>
    </div>
  </PhCard>
</template>
