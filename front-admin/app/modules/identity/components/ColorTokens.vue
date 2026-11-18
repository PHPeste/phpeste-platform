<script setup lang="ts">
import { computed } from 'vue'
import ColorField from './ColorField.vue'
import locale from '../locales/pt-br'
import type { TokenMeta } from '../types'

const MAIN = ['bg', 'brand', 'accent', 'support', 'ink']

const props = defineProps<{ tokens: TokenMeta[], disabled?: boolean }>()
const colors = defineModel<Record<string, string>>({ required: true })

const main = computed(() => MAIN.map((id) => props.tokens.find((token) => token.id === id)).filter((token): token is TokenMeta => !!token))
const others = computed(() => props.tokens.filter((token) => !MAIN.includes(token.id)))

function update(token: string, value: string) {
  colors.value = { ...colors.value, [token]: value }
}
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-3.5">
      <h2 class="text-lg font-semibold">{{ locale.colorsTitle }}</h2>
      <ColorField
        v-for="token in main"
        :key="token.id"
        :model-value="colors[token.id] ?? ''"
        :label="token.label"
        :disabled="disabled"
        @update:model-value="update(token.id, $event)"
      />
      <details class="rounded-(--ph-radius) border border-(--ph-line) px-3 py-2">
        <summary class="min-h-9 cursor-pointer py-1.5 text-sm font-medium">{{ locale.moreColors }}</summary>
        <div class="flex flex-col gap-3.5 pt-3 pb-1">
          <ColorField
            v-for="token in others"
            :key="token.id"
            :model-value="colors[token.id] ?? ''"
            :label="token.label"
            :disabled="disabled"
            @update:model-value="update(token.id, $event)"
          />
        </div>
      </details>
    </div>
  </PhCard>
</template>
