<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { useToast } from '@/application/shared/composables/useToast'
import CriteriaTab from './CriteriaTab.vue'
import FormPreview from './FormPreview.vue'
import GeneralTab from './GeneralTab.vue'
import TracksTab from './TracksTab.vue'
import { useCfpSettings } from '../composables/useCfpSettings'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, readOnly: boolean }>()

const cfp = useCfpSettings(props.editionId)
const { settings, form, tracks, criteria, submittedReviews, state, saving, general, generalErrors, generalDirty, windowErrors, totalWeight } = cfp
const toast = useToast()
const router = useRouter()

const tab = ref('general')
const tabs = computed(() => [
  { id: 'general', label: generalDirty.value ? `${locale.tabs.general} •` : locale.tabs.general },
  { id: 'tracks', label: locale.tabs.tracks },
  { id: 'criteria', label: locale.tabs.criteria },
  { id: 'form', label: locale.tabs.form },
])
const errors = computed(() => ({ ...windowErrors.value, ...generalErrors.value }))
const fieldLabels: Record<string, string> = { opensOn: locale.opensOn, closesOn: locale.closesOn, resultsOn: locale.resultsOn, nordesteQuotaPercent: locale.quota, proposalsPerPerson: locale.perPerson }
const fieldIds = Object.fromEntries(Object.keys(fieldLabels).map((key) => [key, `cfp-${key}`]))

async function save() {
  const result = await cfp.saveGeneral()
  if (result.ok) toast.success(locale.saved)
  else tab.value = 'general'
}

const leaveOpen = ref(false)
const leaving = ref(false)
let pendingPath: string | null = null

onBeforeRouteLeave((to) => {
  if (leaving.value || !generalDirty.value || props.readOnly) return true
  pendingPath = to.fullPath
  leaveOpen.value = true
  return false
})

async function leaveAnyway() {
  leaveOpen.value = false
  if (!pendingPath) return
  leaving.value = true
  await router.push(pendingPath)
}

function onBeforeUnload(event: BeforeUnloadEvent) {
  if (generalDirty.value && !leaving.value) event.preventDefault()
}

onMounted(() => {
  cfp.load()
  window.addEventListener('beforeunload', onBeforeUnload)
})
onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
      </div>
      <div v-if="state === 'ready'" class="flex flex-wrap items-center gap-3">
        <span v-if="generalDirty" class="text-sm font-medium text-(--ph-warn)">{{ locale.unsaved }}</span>
        <PhButton variant="ghost" @click="tab = 'form'">{{ locale.viewForm }}</PhButton>
        <PhButton v-if="!readOnly" :loading="saving" :disabled="!generalDirty" @click="save">{{ locale.save }}</PhButton>
      </div>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>
    <PhFormErrors :errors="generalErrors" :labels="fieldLabels" :field-ids="fieldIds" />

    <PhPageSkeleton v-if="state === 'loading'" :cards="2" :columns="2" :lines="5" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="cfp.load" />

    <PhTabs v-else-if="settings && form" v-model="tab" :items="tabs" :label="locale.tabsLabel">
      <template #default="{ active }">
        <GeneralTab v-if="active === 'general'" v-model="general" :settings="settings" :errors="errors" :disabled="readOnly" />
        <TracksTab v-else-if="active === 'tracks'" :tracks="tracks" :disabled="readOnly" :save="cfp.saveTrack" :remove="cfp.removeTrack" :move="cfp.moveTrack" />
        <CriteriaTab
          v-else-if="active === 'criteria'"
          :criteria="criteria"
          :total-weight="totalWeight"
          :submitted-reviews="submittedReviews"
          :disabled="readOnly"
          :save="cfp.saveCriterion"
          :remove="cfp.removeCriterion"
        />
        <FormPreview v-else :general="general" :status="settings.status" :tracks="tracks" :form="form" />
      </template>
    </PhTabs>

    <PhModal v-model="leaveOpen" :title="locale.leaveTitle">
      <p>{{ locale.leaveText }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="leaveAnyway">{{ locale.leave }}</PhButton>
        <PhButton @click="leaveOpen = false">{{ locale.stay }}</PhButton>
      </template>
    </PhModal>
  </section>
</template>
