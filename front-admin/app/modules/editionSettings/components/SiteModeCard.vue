<script setup lang="ts">
import { computed } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import locale from '../locales/pt-br'
import type { Domain, SiteInput } from '../types'

const props = defineProps<{ editionId: string, domains: Domain[], apiBase: string, errors: Record<string, string>, disabled: boolean }>()
const form = defineModel<SiteInput>({ required: true })
const toast = useToast()

const area = computed(() => props.domains.find((domain) => domain.isPrimary && domain.verified) ?? props.domains.find((domain) => domain.verified) ?? null)
const cfpEndpoint = computed(() => `${props.apiBase}/public/editions/${props.editionId}/cfp`)

async function copy(value: string) {
  try {
    await navigator.clipboard.writeText(value)
    toast.success(locale.copied)
  } catch {
    toast.error(locale.genericError)
  }
}
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-1">
        <h2 class="text-lg font-semibold">{{ locale.siteTitle }}</h2>
        <p class="text-sm text-(--ph-ink-2)">{{ locale.siteHint }}</p>
      </div>
      <fieldset class="grid gap-3 md:grid-cols-2" :disabled="disabled">
        <legend class="sr-only">{{ locale.siteTitle }}</legend>
        <label v-for="mode in (['platform', 'external'] as const)" :key="mode" class="flex cursor-pointer gap-3 rounded-(--ph-radius) border p-4" :class="form.siteMode === mode ? 'border-(--ph-brand) ring-2 ring-(--ph-accent)' : 'border-(--ph-line)'">
          <input v-model="form.siteMode" type="radio" name="site-mode" :value="mode" class="radio radio-primary mt-0.5">
          <span class="flex flex-col gap-0.5">
            <b class="font-medium">{{ mode === 'platform' ? locale.sitePlatform : locale.siteExternal }}</b>
            <span class="text-sm text-(--ph-ink-2)">{{ mode === 'platform' ? locale.sitePlatformHint : locale.siteExternalHint }}</span>
          </span>
        </label>
      </fieldset>

      <template v-if="form.siteMode === 'external'">
        <PhInput v-model="form.externalSiteUrl" type="url" :label="locale.externalUrl" :hint="locale.externalUrlHint" placeholder="https://" :error="errors.externalSiteUrl" :disabled="disabled" />
        <section class="flex flex-col gap-3 rounded-(--ph-radius) bg-(--ph-surface-2) p-4 text-sm" data-testid="site-guide">
          <h3 class="font-semibold">{{ locale.guideTitle }}</h3>
          <ol class="flex list-decimal flex-col gap-3 pl-5">
            <li>{{ area ? locale.guideDomain : locale.guideNoDomain }}</li>
            <li>
              {{ locale.guideLinks }}
              <ul v-if="area" class="mt-1 flex flex-col gap-1">
                <li>{{ locale.linkSubmit }}: <code>{{ area.url }}/cfp</code></li>
                <li>{{ locale.linkMine }}: <code>{{ area.url }}/palestrante/propostas</code></li>
              </ul>
            </li>
            <li class="flex flex-col gap-1.5">
              {{ locale.guideApi }}
              <span class="flex flex-wrap items-center gap-2">
                <span class="text-(--ph-ink-3)">{{ locale.editionId }}:</span>
                <code>{{ editionId }}</code>
                <PhButton variant="ghost" @click="copy(editionId)">{{ locale.copy }}</PhButton>
              </span>
              <code class="break-all">GET {{ cfpEndpoint }}</code>
            </li>
          </ol>
        </section>
      </template>
    </div>
  </PhCard>
</template>
