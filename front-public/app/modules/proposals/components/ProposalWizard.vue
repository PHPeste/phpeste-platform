<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import AboutTalkStep from './AboutTalkStep.vue'
import AboutYouStep from './AboutYouStep.vue'
import AuthPanel from './AuthPanel.vue'
import CfpNotice from './CfpNotice.vue'
import ReviewStep from './ReviewStep.vue'
import WizardProgress from './WizardProgress.vue'
import { longDate } from '../composables/translate'
import { useCfp } from '../composables/useCfp'
import { useProposalWizard } from '../composables/useProposalWizard'
import { useSpeakerAuth } from '../composables/useSpeakerAuth'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, editId: string | null }>()

const { cfp, state: cfpState, load: loadCfp } = useCfp(props.editionId)
const wizard = useProposalWizard(props.editionId, props.editId)
const { step, profile, talk, saveState, savedAt, errors, message, busy, submitted, loaded, missing, isEditingSubmitted } = wizard
const { user, isAuthenticated } = useSpeakerAuth()
const heading = ref<HTMLElement | null>(null)

onMounted(async () => {
  await Promise.all([loadCfp(), wizard.load()])
})

const perPerson = computed(() => (cfp.value?.proposalsPerPerson === 1 ? locale.perPersonOne : locale.perPerson.replace('{n}', String(cfp.value?.proposalsPerPerson ?? 0))))
const saveLabel = computed(() => {
  if (saveState.value === 'saving') return locale.saving
  if (saveState.value === 'error') return locale.saveFailed
  if (saveState.value === 'local') return locale.savedLocal
  if (saveState.value === 'saved' && savedAt.value) return locale.savedAt.replace('{hora}', savedAt.value.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }))
  return ''
})

async function focusHeading() {
  await nextTick()
  heading.value?.focus()
}

async function next() {
  if (await wizard.next()) await focusHeading()
}

function back() {
  wizard.back()
  focusHeading()
}

function edit(target: 1 | 2) {
  step.value = target
  focusHeading()
}

async function submit() {
  if (await wizard.submit()) await focusHeading()
}

watch(isAuthenticated, async (value) => {
  if (!value) return
  await wizard.afterLogin()
  await focusHeading()
})
</script>

<template>
  <section class="mx-auto flex max-w-3xl flex-col gap-6">
    <header class="flex flex-col gap-2">
      <h1 ref="heading" tabindex="-1" class="text-3xl leading-tight font-semibold outline-none">{{ submitted ? locale.submittedTitle : locale.title }}</h1>
      <p v-if="!submitted" class="text-(--ph-ink-2)">{{ locale.intro }} <template v-if="cfp?.anonymousReview">{{ locale.anonymous }}</template></p>
    </header>

    <div v-if="cfpState === 'loading' || !loaded" class="flex flex-col gap-3" role="status" aria-busy="true">
      <div class="skeleton h-4 w-1/3" />
      <div class="skeleton h-40 w-full" />
    </div>
    <div v-else-if="cfpState === 'error' || !cfp" role="alert" class="alert alert-error alert-soft flex flex-wrap justify-between gap-3">
      <span>{{ locale.loadError }}</span>
      <button type="button" class="btn btn-sm" @click="loadCfp">{{ locale.retry }}</button>
    </div>

    <section v-else-if="submitted" class="card border border-(--ph-line) bg-(--ph-surface) p-6" data-testid="submitted">
      <p>
        {{ (cfp.closesAt ? locale.submittedText.replace('{data}', longDate(cfp.closesAt)) : locale.submittedTextNoDate).replace('{titulo}', submitted.title) }}
      </p>
      <div class="mt-4 flex flex-wrap gap-3">
        <NuxtLink to="/palestrante/propostas" class="btn btn-primary">{{ locale.goToMine }}</NuxtLink>
        <button v-if="!isEditingSubmitted" type="button" class="btn btn-ghost" @click="wizard.startOver()">{{ locale.newOne }}</button>
      </div>
    </section>

    <CfpNotice v-else-if="cfp.status !== 'open'" :cfp="cfp" />

    <template v-else>
      <p class="text-sm text-(--ph-ink-2)">
        <template v-if="cfp.closesAt">{{ locale.untilDate.replace('{data}', longDate(cfp.closesAt)) }} </template>{{ perPerson }}
      </p>
      <WizardProgress :step="step" />

      <AuthPanel v-if="step === 1 && !isAuthenticated" :title="locale.authTitle" :text="locale.authText" />

      <form v-else class="flex flex-col gap-6" novalidate @submit.prevent="step === 3 ? submit() : next()">
        <AboutYouStep v-if="step === 1" v-model="profile" :errors="errors" :user-name="user?.name ?? ''" />
        <AboutTalkStep v-else-if="step === 2" v-model="talk" :cfp="cfp" :errors="errors" />
        <ReviewStep v-else :cfp="cfp" :profile="profile" :talk="talk" :missing="missing" @edit="edit" />

        <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-(--ph-line) pt-5">
          <span class="text-xs text-(--ph-ink-3)" role="status" aria-live="polite">{{ step === 2 ? saveLabel : '' }}</span>
          <div class="flex flex-wrap gap-3">
            <button v-if="step > 1" type="button" class="btn btn-ghost" @click="back">{{ locale.back }}</button>
            <button v-if="step === 2 && !isEditingSubmitted" type="button" class="btn btn-ghost" :disabled="saveState === 'saving'" @click="wizard.saveTalk()">{{ locale.saveDraft }}</button>
            <button type="submit" class="btn btn-primary" :disabled="!!busy">
              {{ step === 3 ? (isEditingSubmitted ? locale.saveChanges : locale.submit) : locale.continue }}
            </button>
          </div>
        </div>
      </form>
    </template>
  </section>
</template>
