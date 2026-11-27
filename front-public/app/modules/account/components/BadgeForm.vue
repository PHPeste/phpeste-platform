<script setup lang="ts">
import { useId } from 'vue'
import TextField from '@/application/shared/components/TextField.vue'
import { TSHIRT_SIZES, type BadgeForm } from '../types'
import locale from '../locales/pt-br'

const props = defineProps<{ form: BadgeForm, errors: Record<string, string>, busy: boolean, status: string | null }>()
defineEmits<{ submit: [] }>()
const id = useId()
const form = props.form
</script>

<template>
  <section class="card flex flex-col gap-4 border border-(--ph-line) bg-(--ph-surface) p-6 print:hidden" :aria-labelledby="`${id}-title`">
    <div class="flex flex-col gap-1">
      <h2 :id="`${id}-title`" class="text-lg font-semibold">{{ locale.badgeTitle }}</h2>
      <p class="text-sm text-(--ph-ink-2)">{{ locale.badgeIntro }}</p>
    </div>
    <form class="flex flex-col gap-4" novalidate @submit.prevent="$emit('submit')">
      <TextField v-model="form.holderName" :label="locale.holderName" autocomplete="name" required :maxlength="120" :error="errors.holderName" />
      <TextField v-model="form.badgeName" :label="locale.badgeName" :hint="locale.badgeNameHint" autocomplete="nickname" :maxlength="60" :error="errors.badgeName" />
      <TextField v-model="form.company" :label="locale.company" autocomplete="organization" :maxlength="120" :error="errors.company" />
      <div class="flex flex-col gap-1.5">
        <label :for="`${id}-size`" class="text-sm font-medium">{{ locale.tshirtSize }}</label>
        <select :id="`${id}-size`" v-model="form.tshirtSize" class="select w-full" :class="{ 'select-error': errors.tshirtSize }" :aria-invalid="errors.tshirtSize ? 'true' : undefined" :aria-describedby="errors.tshirtSize ? `${id}-size-error` : undefined">
          <option value="">{{ locale.tshirtChoose }}</option>
          <option v-for="size in TSHIRT_SIZES" :key="size" :value="size">{{ locale.tshirtLabels[size] }}</option>
        </select>
        <p v-if="errors.tshirtSize" :id="`${id}-size-error`" role="alert" class="text-xs text-error">{{ errors.tshirtSize }}</p>
      </div>
      <div class="flex flex-col gap-1.5">
        <label :for="`${id}-a11y`" class="text-sm font-medium">{{ locale.accessibility }}</label>
        <textarea :id="`${id}-a11y`" v-model="form.accessibility" class="textarea w-full" rows="3" maxlength="1000" :class="{ 'textarea-error': errors.accessibility }" :aria-invalid="errors.accessibility ? 'true' : undefined" :aria-describedby="`${id}-a11y-hint${errors.accessibility ? ` ${id}-a11y-error` : ''}`" />
        <p :id="`${id}-a11y-hint`" class="text-xs text-(--ph-ink-3)">{{ locale.accessibilityHint }}</p>
        <p v-if="errors.accessibility" :id="`${id}-a11y-error`" role="alert" class="text-xs text-error">{{ errors.accessibility }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="btn btn-primary" :disabled="busy">{{ locale.save }}</button>
        <p role="status" class="text-sm">{{ status }}</p>
      </div>
    </form>
  </section>
</template>
