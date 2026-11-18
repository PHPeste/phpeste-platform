<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import AccountTab from './AccountTab.vue'
import NotificationsTab from './NotificationsTab.vue'
import SecurityTab from './SecurityTab.vue'
import { useMyProfile } from '../composables/useMyProfile'
import locale from '../locales/pt-br'

const profile = useMyProfile()
const { account, sessions, state, form, busy } = profile
const toast = useToast()
const tab = ref('account')
const errors = ref<Record<string, string>>({})

onMounted(profile.load)

const tabs = computed(() => (['account', 'security', 'notifications'] as const).map((id) => ({ id, label: locale.tabs[id] })))
const dirty = computed(() => profile.isDirty())

async function save() {
  const result = await profile.saveAccount()
  errors.value = result.ok ? {} : result.fieldErrors
  if (result.ok) toast.success(locale.saved)
  else if (!Object.keys(result.fieldErrors).length) toast.error(result.message)
}

async function photo(action: () => Promise<{ ok: boolean, message?: string, fieldErrors?: Record<string, string> }>, message: string) {
  const result = await action()
  errors.value = result.ok ? {} : (result.fieldErrors ?? {})
  if (result.ok) toast.success(message)
  else if (!Object.keys(result.fieldErrors ?? {}).length) toast.error(result.message ?? locale.genericError)
}
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
      </div>
      <PhButton v-if="state === 'ready' && tab === 'account'" :loading="busy === 'account'" :disabled="!dirty" @click="save">{{ locale.save }}</PhButton>
    </header>

    <PhPageSkeleton v-if="state === 'loading'" :cards="1" :lines="6" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="profile.load" />

    <PhTabs v-else-if="account" v-model="tab" :items="tabs" :label="locale.tabsLabel">
      <template #default="{ active }">
        <AccountTab
          v-if="active === 'account'"
          v-model="form"
          :account="account"
          :errors="errors"
          :photo-busy="busy === 'photo'"
          @upload="photo(() => profile.uploadPhoto($event), locale.photoSaved)"
          @remove="photo(profile.removePhoto, locale.photoRemoved)"
        />
        <SecurityTab
          v-else-if="active === 'security'"
          :has-password="account.hasPassword"
          :sessions="sessions"
          :busy="busy"
          :change-password="profile.changePassword"
          :end-session="profile.endSession"
        />
        <NotificationsTab v-else :options="account.notifications" :busy="busy" :toggle="profile.setNotification" />
      </template>
    </PhTabs>
  </section>
</template>
