<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { useEdition } from '@/application/shared/composables/useEdition'
import { useToast } from '@/application/shared/composables/useToast'
import AuditTab from './AuditTab.vue'
import DomainsCard from './DomainsCard.vue'
import GeneralTab from './GeneralTab.vue'
import PrivacyTab from './PrivacyTab.vue'
import SiteModeCard from './SiteModeCard.vue'
import StageCard from './StageCard.vue'
import { useEditionSettings } from '../composables/useEditionSettings'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, readOnly: boolean }>()

const editions = useEdition()
const settings = useEditionSettings(props.editionId, () => editions.load())
const { edition, domains, state, saving, general, site, privacy, dirty, anyDirty } = settings
const toast = useToast()
const router = useRouter()
const apiBase = String(useRuntimeConfig().public.apiBase)

const TAB_FOR: Record<string, string> = {
  name: 'general', slug: 'general', startsOn: 'general', endsOn: 'general', city: 'general', state: 'general', timezone: 'general',
  siteMode: 'site', externalSiteUrl: 'site', privacyContactEmail: 'privacy', privacyPolicyUrl: 'privacy', anonymizeAfterEvent: 'privacy',
}

const tab = ref('general')
const errors = ref<Record<string, string>>({})
const tabs = computed(() => (['general', 'site', 'privacy', 'audit'] as const).map((id) => ({
  id,
  label: id !== 'audit' && dirty.value[id] ? `${locale.tabs[id]} •` : locale.tabs[id],
})))
const fieldLabels: Record<string, string> = {
  name: locale.name, slug: locale.slug, startsOn: locale.startsOn, endsOn: locale.endsOn, city: locale.city, state: locale.state, timezone: locale.timezone,
  siteMode: locale.siteTitle, externalSiteUrl: locale.externalUrl, privacyContactEmail: locale.privacyEmail, privacyPolicyUrl: locale.privacyPolicy, anonymizeAfterEvent: locale.anonymize,
}
const fieldIds = Object.fromEntries(Object.keys(fieldLabels).map((key) => [key, `settings-${key}`]))
const slugLocked = computed(() => !!edition.value && edition.value.status !== 'draft')

async function save() {
  const result = await settings.save()
  if (result.ok) {
    errors.value = {}
    toast.success(locale.saved)
    return
  }
  errors.value = result.fieldErrors
  const first = Object.keys(result.fieldErrors)[0]
  if (first && TAB_FOR[first]) tab.value = TAB_FOR[first]!
  if (!first) toast.error(result.message)
}

const leaveOpen = ref(false)
const leaving = ref(false)
let pendingPath: string | null = null

onBeforeRouteLeave((to) => {
  if (leaving.value || !anyDirty.value || props.readOnly) return true
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
  if (anyDirty.value && !leaving.value) event.preventDefault()
}

onMounted(() => {
  settings.load()
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
      <div v-if="state === 'ready' && !readOnly && tab !== 'audit'" class="flex flex-wrap items-center gap-3">
        <span v-if="anyDirty" class="text-sm font-medium text-(--ph-warn)">{{ locale.unsaved }}</span>
        <PhButton :loading="saving === 'all'" :disabled="!anyDirty" @click="save">{{ locale.save }}</PhButton>
      </div>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>
    <PhFormErrors :errors="errors" :labels="fieldLabels" :field-ids="fieldIds" />

    <PhPageSkeleton v-if="state === 'loading'" :cards="2" :lines="5" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="settings.load" />

    <PhTabs v-else-if="edition" v-model="tab" :items="tabs" :label="locale.tabsLabel">
      <template #default="{ active }">
        <div v-if="active === 'general'" class="flex flex-col gap-6">
          <GeneralTab v-model="general" :slug-locked="slugLocked" :errors="errors" :disabled="readOnly" />
          <p v-if="anyDirty && !readOnly && edition.statusOptions.length" class="text-sm text-(--ph-warn)">{{ locale.stageDirty }}</p>
          <StageCard
            :status-label="edition.statusLabel"
            :options="edition.statusOptions"
            :disabled="readOnly || anyDirty"
            :change="settings.changeStatus"
            @changed="toast.success(locale.moved.replace('{etapa}', $event))"
            @failed="toast.error($event)"
          />
        </div>
        <div v-else-if="active === 'site'" class="flex flex-col gap-6">
          <DomainsCard
            :domains="domains"
            :disabled="readOnly"
            :add="settings.addDomain"
            :verify="settings.verifyDomain"
            :make-primary="settings.makePrimary"
            :remove="settings.removeDomain"
          />
          <SiteModeCard v-model="site" :edition-id="edition.id" :domains="domains" :api-base="apiBase" :errors="errors" :disabled="readOnly" />
        </div>
        <PrivacyTab v-else-if="active === 'privacy'" v-model="privacy" :errors="errors" :disabled="readOnly" />
        <AuditTab v-else :edition-id="edition.id" />
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
