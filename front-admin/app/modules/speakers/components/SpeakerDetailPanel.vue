<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import ConfirmationTab from './ConfirmationTab.vue'
import LogisticsTab from './LogisticsTab.vue'
import ProfileTab from './ProfileTab.vue'
import TalksTab from './TalksTab.vue'
import { STATUS_TONE } from './statusTone'
import { longDate } from '../composables/format'
import { useSpeaker } from '../composables/useSpeaker'
import locale from '../locales/pt-br'
import type { SpeakerTalk } from '../types'

const props = defineProps<{ editionId: string, userId: string, readOnly: boolean }>()

const detail = useSpeaker(props.editionId, props.userId)
const { speaker, state } = detail
const toast = useToast()
const tab = ref('profile')
const busy = ref(false)

onMounted(detail.load)

const tabs = computed(() => (['profile', 'talks', 'logistics', 'confirmation'] as const).map((id) => ({ id, label: locale.tabs[id] })))
const mainTalk = computed(() => speaker.value?.talks.find((talk) => talk.status === 'confirmed') ?? speaker.value?.talks[0] ?? null)
const confirmed = computed(() => speaker.value?.talks.some((talk) => talk.status === 'confirmed') ?? false)
const attended = computed(() => speaker.value?.talks.some((talk) => talk.attendedAt) ?? false)
const subtitle = computed(() => [speaker.value?.jobTitle, [speaker.value?.city, speaker.value?.state].filter(Boolean).join(' · ')].filter(Boolean).join(' · '))

async function toggleAttendance() {
  if (!speaker.value) return
  busy.value = true
  const result = await detail.setAttendance(!attended.value)
  busy.value = false
  if (!result.ok) return toast.error(result.message)
  toast.success((attended.value ? locale.markedPresent : locale.unmarkedPresent).replace('{nome}', speaker.value.name))
}

async function remind(talk: SpeakerTalk) {
  const result = await detail.remind(talk.id)
  if (!result.ok) return toast.error(result.message)
  if (result.data.reminded) toast.success(locale.remindedOneTo.replace('{nome}', speaker.value?.name ?? ''))
  else toast.info(locale.remindedTooSoon)
}
</script>

<template>
  <section class="flex flex-col gap-6">
    <NuxtLink to="/palestrantes" class="link link-hover self-start text-sm">{{ locale.back }}</NuxtLink>

    <p v-if="state === 'loading'" role="status" aria-live="polite" class="flex items-center gap-2 text-(--ph-ink-2)">
      <span class="loading loading-spinner loading-sm" aria-hidden="true" />{{ locale.loading }}
    </p>
    <div v-else-if="state === 'error'" role="alert" class="alert alert-error alert-soft">
      <span>{{ locale.detailLoadError }}</span>
      <PhButton variant="ghost" @click="detail.load">{{ locale.retry }}</PhButton>
    </div>
    <p v-else-if="state === 'not-found'" role="alert" class="alert alert-warning alert-soft">{{ locale.notFound }}</p>

    <template v-else-if="speaker">
      <header class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <img v-if="speaker.photoUrl" :src="speaker.photoUrl" alt="" class="size-16 rounded-full object-cover">
          <span v-else class="grid size-16 place-items-center rounded-full bg-(--ph-accent) text-xl font-semibold text-(--ph-ink)" aria-hidden="true">{{ speaker.initials }}</span>
          <div class="flex flex-col gap-1.5">
            <h1 class="text-3xl leading-tight font-semibold">{{ speaker.name }}</h1>
            <p v-if="subtitle" class="text-(--ph-ink-2)">{{ subtitle }}</p>
            <div class="flex flex-wrap gap-2">
              <PhBadge v-if="mainTalk" :status="STATUS_TONE[mainTalk.status]">{{ mainTalk.statusLabel }}</PhBadge>
              <PhBadge v-if="speaker.nordeste" status="info" :dot="false">{{ locale.nordeste }}</PhBadge>
              <PhBadge v-if="attended" status="ok" :dot="false">{{ locale.present }}</PhBadge>
            </div>
          </div>
        </div>
        <PhButton v-if="confirmed && !readOnly" :variant="attended ? 'ghost' : 'primary'" :loading="busy" @click="toggleAttendance">
          {{ attended ? locale.unmarkPresent : locale.markPresent }}
        </PhButton>
      </header>

      <PhTabs v-model="tab" :items="tabs" :label="locale.tabsLabel">
        <template #default="{ active }">
          <ProfileTab v-if="active === 'profile'" :speaker="speaker" />
          <TalksTab v-else-if="active === 'talks'" :talks="speaker.talks" />
          <LogisticsTab
            v-else-if="active === 'logistics'"
            :logistics="speaker.logistics"
            :can-view-private-data="speaker.canViewPrivateData"
            :disabled="readOnly"
            :save="detail.saveLogistics"
            @saved="toast.success(locale.logisticsSaved)"
          />
          <ConfirmationTab
            v-else
            :talks="speaker.talks"
            :disabled="readOnly"
            :extend="detail.extendDeadline"
            :remind="remind"
            @extended="toast.success(locale.extended.replace('{data}', longDate($event)))"
          />
        </template>
      </PhTabs>
    </template>
  </section>
</template>
