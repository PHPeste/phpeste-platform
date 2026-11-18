<script setup lang="ts">
import SelectField from './SelectField.vue'
import TextAreaField from './TextAreaField.vue'
import TextField from './TextField.vue'
import locale from '../locales/pt-br'
import type { ProfileForm } from '../types'

const UFS = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'].map((uf) => ({ value: uf, label: uf }))

defineProps<{ errors: Record<string, string>, userName: string }>()
const profile = defineModel<ProfileForm>({ required: true })
</script>

<template>
  <div class="flex flex-col gap-5">
    <div class="flex flex-col gap-1">
      <h2 class="text-xl font-semibold">{{ locale.profileTitle }}</h2>
      <p class="text-sm text-(--ph-ink-2)">{{ locale.loggedAs.replace('{nome}', userName) }} {{ locale.profileHint }}</p>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <TextField v-model="profile.displayName" :label="locale.displayName" required :maxlength="120" :error="errors.displayName" />
      <TextField v-model="profile.jobTitle" :label="locale.jobTitle" :maxlength="120" :error="errors.jobTitle" />
      <TextField v-model="profile.company" :label="locale.company" :maxlength="120" :error="errors.company" />
      <TextField v-model="profile.city" :label="locale.city" :maxlength="120" :error="errors.city" />
      <SelectField v-model="profile.state" :label="locale.state" :options="UFS" :placeholder="locale.chooseState" :hint="locale.stateHint" required :error="errors.state" />
    </div>
    <TextAreaField v-model="profile.bio" :label="locale.bio" :maxlength="400" :rows="3" :error="errors.bio" />
    <fieldset class="flex flex-col gap-3">
      <legend class="mb-2 text-sm font-semibold">{{ locale.links }}</legend>
      <div class="grid gap-4 sm:grid-cols-3">
        <TextField v-model="profile.github" type="url" :label="locale.github" :hint="locale.linkHint" :error="errors.github" />
        <TextField v-model="profile.linkedin" type="url" :label="locale.linkedin" :error="errors.linkedin" />
        <TextField v-model="profile.website" type="url" :label="locale.website" :error="errors.website" />
      </div>
    </fieldset>
    <details class="rounded-box border border-(--ph-line) p-4">
      <summary class="cursor-pointer text-sm font-semibold">{{ locale.privateTitle }}</summary>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <TextAreaField v-model="profile.accessibilityNeeds" :label="locale.accessibility" :rows="2" :error="errors.accessibilityNeeds" />
        <TextAreaField v-model="profile.dietaryRestrictions" :label="locale.dietary" :rows="2" :error="errors.dietaryRestrictions" />
      </div>
    </details>
  </div>
</template>
