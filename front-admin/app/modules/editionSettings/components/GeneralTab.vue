<script setup lang="ts">
import { computed } from 'vue'
import locale from '../locales/pt-br'
import type { GeneralInput } from '../types'

const UFS = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO']
const ZONES = ['America/Fortaleza', 'America/Recife', 'America/Maceio', 'America/Bahia', 'America/Belem', 'America/Araguaina', 'America/Sao_Paulo', 'America/Cuiaba', 'America/Campo_Grande', 'America/Manaus', 'America/Porto_Velho', 'America/Boa_Vista', 'America/Rio_Branco', 'America/Noronha']

const props = defineProps<{ slugLocked: boolean, errors: Record<string, string>, disabled: boolean }>()
const form = defineModel<GeneralInput>({ required: true })

const stateOptions = [{ value: '', label: locale.noState }, ...UFS.map((uf) => ({ value: uf, label: uf }))]
const zoneOptions = computed(() => [...new Set([form.value.timezone, ...ZONES])].map((zone) => ({ value: zone, label: zone.replace('America/', 'América/').replace('_', ' ') })))
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-4">
      <h2 class="text-lg font-semibold">{{ locale.generalTitle }}</h2>
      <div class="grid gap-4 md:grid-cols-2">
        <PhInput v-model="form.name" :label="locale.name" :error="errors.name" :disabled="disabled" required />
        <PhInput v-model="form.slug" :label="locale.slug" :hint="props.slugLocked ? locale.slugLocked : undefined" :error="errors.slug" :disabled="disabled || slugLocked" />
        <PhInput v-model="form.startsOn" type="date" :label="locale.startsOn" :error="errors.startsOn" :disabled="disabled" />
        <PhInput v-model="form.endsOn" type="date" :label="locale.endsOn" :error="errors.endsOn" :disabled="disabled" />
        <PhInput v-model="form.city" :label="locale.city" :error="errors.city" :disabled="disabled" />
        <PhSelect v-model="form.state" :label="locale.state" :options="stateOptions" :error="errors.state" :disabled="disabled" />
        <PhSelect v-model="form.timezone" :label="locale.timezone" :options="zoneOptions" :error="errors.timezone" :disabled="disabled" />
      </div>
    </div>
  </PhCard>
</template>
