<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import locale from '../locales/pt-br'
import type { PasswordInput, Result, Session } from '../types'

const props = defineProps<{
  hasPassword: boolean
  sessions: Session[]
  busy: string | null
  changePassword: (input: PasswordInput) => Promise<Result>
  endSession: (session: Session) => Promise<Result>
}>()

const toast = useToast()
const password = reactive<PasswordInput>({ currentPassword: '', password: '', passwordConfirmation: '' })
const errors = ref<Record<string, string>>({})
const ending = ref<Session | null>(null)
const endOpen = ref(false)

async function submit() {
  errors.value = {}
  if (props.hasPassword && !password.currentPassword) errors.value.currentPassword = locale.required
  if (!password.password) errors.value.password = locale.required
  if (Object.keys(errors.value).length) return

  const result = await props.changePassword({ ...password })
  if (!result.ok) {
    errors.value = result.fieldErrors
    if (!Object.keys(result.fieldErrors).length) toast.error(result.message)
    return
  }
  Object.assign(password, { currentPassword: '', password: '', passwordConfirmation: '' })
  toast.success(locale.passwordChanged)
}

function ask(session: Session) {
  ending.value = session
  endOpen.value = true
}

async function confirmEnd() {
  const session = ending.value
  if (!session) return
  const result = await props.endSession(session)
  endOpen.value = false
  if (result.ok) toast.success(locale.ended.replace('{nome}', session.name))
  else toast.error(result.message)
}

function lastUsed(session: Session) {
  if (session.current) return locale.now
  if (!session.lastUsedAt) return locale.neverUsed
  return locale.lastUsed.replace('{quando}', new Date(session.lastUsedAt).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }))
}
</script>

<template>
  <div class="grid items-start gap-6 lg:grid-cols-2">
    <PhCard>
      <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
        <h2 class="text-lg font-semibold">{{ locale.passwordTitle }}</h2>
        <p v-if="!hasPassword" class="text-sm text-(--ph-ink-2)">{{ locale.noPassword }}</p>
        <PhInput v-if="hasPassword" v-model="password.currentPassword" type="password" :label="locale.currentPassword" autocomplete="current-password" :error="errors.currentPassword" />
        <PhInput v-model="password.password" type="password" :label="locale.newPassword" :hint="locale.newPasswordHint" autocomplete="new-password" :error="errors.password" />
        <PhInput v-model="password.passwordConfirmation" type="password" :label="locale.repeatPassword" autocomplete="new-password" :error="errors.passwordConfirmation" />
        <PhButton type="submit" class="self-start" :loading="busy === 'password'">{{ hasPassword ? locale.changePassword : locale.setPassword }}</PhButton>
      </form>
    </PhCard>

    <PhCard>
      <div class="flex flex-col gap-3">
        <div class="flex flex-col gap-1">
          <h2 class="text-lg font-semibold">{{ locale.sessionsTitle }}</h2>
          <p class="text-sm text-(--ph-ink-2)">{{ locale.sessionsHint }}</p>
        </div>
        <ul class="flex flex-col">
          <li v-for="session in sessions" :key="session.id" class="flex items-center justify-between gap-3 border-t border-(--ph-line) py-3 first:border-t-0" :data-session="session.id">
            <div class="flex min-w-0 flex-col">
              <b class="font-medium">{{ session.current ? locale.thisDevice : session.name }}</b>
              <span class="text-xs text-(--ph-ink-3)">{{ session.current ? `${session.name} · ${lastUsed(session)}` : lastUsed(session) }}</span>
            </div>
            <PhBadge v-if="session.current" status="ok">{{ locale.active }}</PhBadge>
            <PhButton v-else variant="ghost" :loading="busy === `session:${session.id}`" :aria-label="locale.endLabel.replace('{nome}', session.name)" @click="ask(session)">{{ locale.end }}</PhButton>
          </li>
        </ul>
      </div>
    </PhCard>

    <PhModal v-model="endOpen" :title="locale.endTitle.replace('{nome}', ending?.name ?? '')">
      <p>{{ locale.endText }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="endOpen = false">{{ locale.cancel }}</PhButton>
        <PhButton variant="danger" @click="confirmEnd">{{ locale.end }}</PhButton>
      </template>
    </PhModal>
  </div>
</template>
