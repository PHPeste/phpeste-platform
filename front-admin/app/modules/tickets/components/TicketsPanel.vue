<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import CouponsTab from './CouponsTab.vue'
import SalesSettingsTab from './SalesSettingsTab.vue'
import TicketTypesTab from './TicketTypesTab.vue'
import { useTickets } from '../composables/useTickets'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, readOnly: boolean, canEdit: boolean }>()

const tickets = useTickets(props.editionId)
const { types, settings, coupons, state, salesPaused } = tickets
const disabled = computed(() => props.readOnly || !props.canEdit)

const tab = ref('types')
const tabs = [
  { id: 'types', label: locale.tabs.types },
  { id: 'coupons', label: locale.tabs.coupons },
  { id: 'settings', label: locale.tabs.settings },
]

const capacityLine = computed(() => {
  if (!settings.value) return ''
  return settings.value.capacity === null
    ? locale.capacityFree.replace('{sold}', String(settings.value.sold))
    : locale.capacityUsed.replace('{sold}', String(settings.value.sold)).replace('{capacity}', String(settings.value.capacity))
})

onMounted(() => tickets.load())
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-col gap-1.5">
      <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
      <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
      <p v-if="state === 'ready'" class="text-sm text-(--ph-ink-3) tabular-nums">{{ capacityLine }}</p>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>
    <div v-if="state === 'ready' && salesPaused" role="alert" class="alert alert-warning alert-soft flex flex-wrap items-center justify-between gap-3" data-testid="sales-paused">
      <div class="flex flex-col gap-1">
        <strong>{{ locale.pausedTitle }}</strong>
        <span class="text-sm">{{ locale.pausedText }}</span>
      </div>
      <PhButton variant="secondary" to="/pagamentos">{{ locale.pausedAction }}</PhButton>
    </div>

    <PhPageSkeleton v-if="state === 'loading'" :cards="2" :lines="5" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="tickets.load" />

    <PhTabs v-else-if="settings" v-model="tab" :items="tabs" :label="locale.tabsLabel">
      <template #default="{ active }">
        <TicketTypesTab
          v-if="active === 'types'"
          :types="types"
          :fee-basis-points="settings.feeBasisPoints"
          :disabled="disabled"
          :save-type="tickets.saveType"
          :remove-type="tickets.removeType"
          :move-type="tickets.moveType"
          :save-batch="tickets.saveBatch"
          :close-batch="tickets.closeBatch"
          :remove-batch="tickets.removeBatch"
        />
        <CouponsTab v-else-if="active === 'coupons'" :coupons="coupons" :types="types" :disabled="disabled" :save="tickets.saveCoupon" :remove="tickets.removeCoupon" />
        <SalesSettingsTab v-else :settings="settings" :disabled="disabled" :save="tickets.saveSettings" />
      </template>
    </PhTabs>
  </section>
</template>
