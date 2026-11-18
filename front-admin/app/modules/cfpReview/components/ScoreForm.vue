<script setup lang="ts">
import { ref } from 'vue'
import locale from '../locales/pt-br'
import type { Criterion } from '../types'

defineProps<{ criteria: Criterion[], scores: Record<string, number>, active: number, busy: 'draft' | 'submit' | null, error: string | null }>()
const comment = defineModel<string>('comment', { required: true })
const emit = defineEmits<{ score: [criterionId: string, score: number], focusCriterion: [index: number], skip: [], draft: [], submit: [] }>()

const commentField = ref<HTMLTextAreaElement | null>(null)
defineExpose({ focusComment: () => commentField.value?.focus() })
</script>

<template>
  <section class="flex flex-col gap-4">
    <h3 class="text-lg font-semibold">{{ locale.yourReview }}</h3>
    <fieldset
      v-for="(criterion, index) in criteria"
      :key="criterion.id"
      class="flex flex-wrap items-center justify-between gap-3 rounded-(--ph-radius) border px-3.5 py-3"
      :class="index === active ? 'border-(--ph-brand) bg-(--ph-surface-2)' : 'border-(--ph-line)'"
      :data-active="index === active ? 'true' : undefined"
      @focusin="emit('focusCriterion', index)"
    >
      <legend class="sr-only">{{ criterion.name }}</legend>
      <div class="flex min-w-0 flex-col">
        <span class="font-medium" aria-hidden="true">{{ criterion.name }} <span class="text-xs font-normal text-(--ph-ink-3)">{{ locale.weight.replace('{n}', String(criterion.weight)) }}</span></span>
        <span v-if="criterion.description" class="text-xs text-(--ph-ink-2)">{{ criterion.description }}</span>
      </div>
      <div class="flex gap-1.5">
        <label v-for="n in 5" :key="n" class="cursor-pointer">
          <input
            type="radio"
            class="peer sr-only"
            :name="`score-${criterion.id}`"
            :value="n"
            :checked="scores[criterion.id] === n"
            :aria-label="locale.scoreOf.replace('{n}', String(n)).replace('{criterio}', criterion.name)"
            @change="emit('score', criterion.id, n)"
          >
          <span
            class="grid size-10 place-items-center rounded-(--ph-radius) border border-(--ph-line) font-semibold tabular-nums peer-checked:border-(--ph-brand) peer-checked:bg-(--ph-brand) peer-checked:text-(--ph-on-brand) peer-focus-visible:ring-2 peer-focus-visible:ring-(--ph-accent)"
            aria-hidden="true"
          >{{ n }}</span>
        </label>
      </div>
    </fieldset>
    <div class="flex flex-col gap-1.5">
      <label for="review-comment" class="text-sm font-medium">{{ locale.comment }}</label>
      <textarea id="review-comment" ref="commentField" v-model="comment" class="textarea w-full" rows="3" maxlength="3000" aria-describedby="review-comment-hint" />
      <p id="review-comment-hint" class="text-xs text-(--ph-ink-3)">{{ locale.commentHint }}</p>
    </div>
    <p v-if="error" role="alert" class="alert alert-error alert-soft text-sm">{{ error }}</p>
    <div class="flex flex-wrap justify-end gap-3">
      <PhButton variant="ghost" :disabled="!!busy" @click="emit('skip')">{{ locale.skip }}</PhButton>
      <PhButton variant="ghost" :loading="busy === 'draft'" :disabled="!!busy" @click="emit('draft')">{{ locale.saveDraft }}</PhButton>
      <PhButton :loading="busy === 'submit'" :disabled="!!busy" @click="emit('submit')">{{ locale.submitNext }}</PhButton>
    </div>
  </section>
</template>
