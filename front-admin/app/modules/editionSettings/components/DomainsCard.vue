<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import locale from '../locales/pt-br'
import type { Domain, SaveResult } from '../types'

const props = defineProps<{
  domains: Domain[]
  disabled: boolean
  add: (host: string) => Promise<SaveResult>
  verify: (domain: Domain) => Promise<boolean | string>
  makePrimary: (domain: Domain) => Promise<SaveResult>
  remove: (domain: Domain) => Promise<SaveResult>
}>()

const toast = useToast()
const host = ref('')
const error = ref<string | null>(null)
const busy = ref<string | null>(null)
const removing = ref<Domain | null>(null)
const removeOpen = ref(false)

async function submit() {
  if (!host.value.trim()) return
  busy.value = 'add'
  const result = await props.add(host.value)
  busy.value = null
  if (!result.ok) {
    error.value = result.fieldErrors.host ?? result.message
    return
  }
  toast.success(locale.domainAdded.replace('{host}', host.value.trim().toLowerCase()))
  host.value = ''
  error.value = null
}

async function runVerify(domain: Domain) {
  busy.value = domain.id
  const result = await props.verify(domain)
  busy.value = null
  if (result === true) toast.success(locale.verifiedOk.replace('{host}', domain.host))
  else toast.warning(typeof result === 'string' ? result : locale.verifyFailed.replace('{host}', domain.host))
}

async function primary(domain: Domain) {
  const result = await props.makePrimary(domain)
  if (result.ok) toast.success(locale.madePrimary.replace('{host}', domain.host))
  else toast.error(result.message)
}

async function confirmRemove() {
  const domain = removing.value
  if (!domain) return
  const result = await props.remove(domain)
  removeOpen.value = false
  if (result.ok) toast.success(locale.removed.replace('{host}', domain.host))
  else toast.error(result.message)
}
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-1">
        <h2 class="text-lg font-semibold">{{ locale.domainsTitle }}</h2>
        <p class="text-sm text-(--ph-ink-2)">{{ locale.domainsHint }}</p>
      </div>
      <p v-if="!domains.length" class="text-sm text-(--ph-ink-2)">{{ locale.noDomains }}</p>
      <ul v-else class="flex flex-col gap-3">
        <li v-for="domain in domains" :key="domain.id" class="flex flex-col gap-3 rounded-(--ph-radius) border border-(--ph-line) p-4" :data-host="domain.host">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
              <a :href="domain.url" target="_blank" rel="noopener noreferrer" class="link font-medium">{{ domain.host }}</a>
              <PhBadge v-if="domain.isPrimary" status="info" :dot="false">{{ locale.primary }}</PhBadge>
              <PhBadge :status="domain.verified ? 'ok' : 'warn'">{{ domain.verified ? locale.verified : locale.pending }}</PhBadge>
              <PhBadge v-if="domain.isPlatform" :dot="false">{{ locale.platform }}</PhBadge>
            </div>
            <div v-if="!disabled" class="flex flex-wrap gap-2">
              <PhButton v-if="!domain.verified" variant="secondary" :loading="busy === domain.id" @click="runVerify(domain)">{{ locale.verify }}</PhButton>
              <PhButton v-if="domain.verified && !domain.isPrimary" variant="ghost" @click="primary(domain)">{{ locale.makePrimary }}</PhButton>
              <PhButton variant="ghost" :aria-label="locale.removeDomain.replace('{host}', domain.host)" @click="removing = domain; removeOpen = true">×</PhButton>
            </div>
          </div>
          <div v-if="!domain.verified && domain.cname && domain.txt" class="flex flex-col gap-2 text-sm">
            <p>{{ locale.dnsTitle }}</p>
            <div class="overflow-x-auto">
              <table class="table table-sm">
                <thead><tr><th scope="col" /><th scope="col">{{ locale.recordName }}</th><th scope="col">{{ locale.recordValue }}</th></tr></thead>
                <tbody>
                  <tr><th scope="row">{{ locale.cnameOption }}</th><td><code>{{ domain.cname.name }}</code></td><td><code>{{ domain.cname.value }}</code></td></tr>
                  <tr><th scope="row">{{ locale.txtOption }}</th><td><code>{{ domain.txt.name }}</code></td><td><code class="break-all">{{ domain.txt.value }}</code></td></tr>
                </tbody>
              </table>
            </div>
            <p v-if="domain.lastCheckError" class="text-xs text-(--ph-warn)">{{ locale.lastError.replace('{erro}', domain.lastCheckError) }}</p>
          </div>
        </li>
      </ul>
      <form v-if="!disabled" class="flex flex-wrap items-end gap-3" @submit.prevent="submit">
        <div class="min-w-64 flex-1">
          <PhInput v-model="host" :label="locale.newDomain" :hint="locale.newDomainHint" :error="error" />
        </div>
        <PhButton type="submit" variant="secondary" :loading="busy === 'add'" :disabled="!host.trim()">{{ locale.add }}</PhButton>
      </form>
    </div>
    <PhModal v-model="removeOpen" :title="locale.removeTitle.replace('{host}', removing?.host ?? '')">
      <p>{{ locale.removeText }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="removeOpen = false">{{ locale.cancel }}</PhButton>
        <PhButton variant="danger" @click="confirmRemove">{{ locale.remove }}</PhButton>
      </template>
    </PhModal>
  </PhCard>
</template>
