<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { EditionRole } from '@/modules/shell/types'
import locale from '../locales/pt-br'
import type { TeamMember } from '../types'

const props = defineProps<{
  member: TeamMember | null
  isSelf: boolean
  lastOrganizer: boolean
  roleOptions: Array<{ value: string, label: string }>
  changeRole: (member: TeamMember, role: EditionRole) => Promise<string | null>
  remove: (member: TeamMember) => Promise<string | null>
}>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ changed: [member: TeamMember, role: EditionRole], removed: [member: TeamMember] }>()

const role = ref<EditionRole>('voluntario')
const confirming = ref(false)
const busy = ref<'save' | 'remove' | null>(null)
const error = ref<string | null>(null)

watch(open, (value) => {
  if (!value || !props.member) return
  role.value = props.member.role
  confirming.value = false
  error.value = null
})

const blocksRoleChange = computed(() => props.lastOrganizer && role.value !== 'organizador')
const name = computed(() => props.member?.name ?? '')

async function save() {
  if (!props.member || blocksRoleChange.value) return
  busy.value = 'save'
  error.value = await props.changeRole(props.member, role.value)
  busy.value = null
  if (!error.value) {
    emit('changed', props.member, role.value)
    open.value = false
  }
}

async function confirmRemove() {
  if (!props.member) return
  busy.value = 'remove'
  error.value = await props.remove(props.member)
  busy.value = null
  if (!error.value) {
    emit('removed', props.member)
    open.value = false
  }
}
</script>

<template>
  <PhModal v-model="open" :title="confirming ? locale.removeTitle.replace('{nome}', name) : locale.editTitle.replace('{nome}', name)">
    <div v-if="member" class="flex flex-col gap-4">
      <template v-if="!confirming">
        <PhSelect v-model="role" :label="locale.role" :options="roleOptions" />
        <p v-if="lastOrganizer" class="text-sm text-(--ph-ink-2)" :role="blocksRoleChange ? 'alert' : undefined">{{ locale.lastOrganizer }}</p>
        <div class="border-t border-(--ph-line) pt-4">
          <PhButton variant="ghost" class="text-error" :disabled="lastOrganizer" @click="confirming = true">{{ locale.remove }}</PhButton>
        </div>
      </template>
      <p v-else>{{ isSelf ? locale.removeSelf : locale.removeConfirm.replace('{nome}', name) }}</p>
      <p v-if="error" role="alert" class="alert alert-error alert-soft text-sm">{{ error }}</p>
    </div>
    <template #footer>
      <template v-if="!confirming">
        <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
        <PhButton :loading="busy === 'save'" :disabled="blocksRoleChange || role === member?.role" @click="save">{{ locale.save }}</PhButton>
      </template>
      <template v-else>
        <PhButton variant="ghost" @click="confirming = false">{{ locale.back }}</PhButton>
        <PhButton variant="danger" :loading="busy === 'remove'" @click="confirmRemove">{{ locale.remove }}</PhButton>
      </template>
    </template>
  </PhModal>
</template>
