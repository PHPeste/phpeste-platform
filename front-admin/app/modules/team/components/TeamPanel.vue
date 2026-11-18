<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import type { EditionRole } from '@/modules/shell/types'
import { useEdition } from '@/application/shared/composables/useEdition'
import { useToast } from '@/application/shared/composables/useToast'
import { HOME_AFTER_LOGIN } from '@/application/shared/layout/redirect'
import { useAuthStore } from '@/stores/authStore'
import InviteModal from './InviteModal.vue'
import MemberModal from './MemberModal.vue'
import RoleMatrix from './RoleMatrix.vue'
import TeamSummary from './TeamSummary.vue'
import TeamTable from './TeamTable.vue'
import { useTeam } from '../composables/useTeam'
import locale from '../locales/pt-br'
import type { TeamInvitation, TeamMember } from '../types'

const props = defineProps<{ editionId: string, readOnly: boolean }>()

const team = useTeam(props.editionId)
const { members, invitations, summary, roles, permissions, state, roleOptions } = team
const toast = useToast()
const router = useRouter()
const edition = useEdition()
const currentUserId = computed(() => useAuthStore().user?.id ?? null)

const inviteOpen = ref(false)
const editing = ref<TeamMember | null>(null)
const editOpen = ref(false)
const cancelling = ref<TeamInvitation | null>(null)
const cancelOpen = ref(false)
const busyId = ref<string | null>(null)

onMounted(team.load)

const lastOrganizer = computed(() => editing.value?.role === 'organizador' && (summary.value?.byRole.organizador ?? 0) <= 1)
const roleLabel = (role: EditionRole) => roles.value.find((item) => item.id === role)?.label ?? role

function edit(member: TeamMember) {
  editing.value = member
  editOpen.value = true
}

async function resend(invitation: TeamInvitation) {
  busyId.value = invitation.id
  const error = await team.resend(invitation)
  busyId.value = null
  if (error) toast.error(error)
  else toast.success(locale.inviteResent.replace('{email}', invitation.email))
}

function askCancel(invitation: TeamInvitation) {
  cancelling.value = invitation
  cancelOpen.value = true
}

async function confirmCancel() {
  const invitation = cancelling.value
  if (!invitation) return
  busyId.value = invitation.id
  const error = await team.cancel(invitation)
  busyId.value = null
  cancelOpen.value = false
  if (error) toast.error(error)
  else toast.success(locale.inviteCancelled.replace('{email}', invitation.email))
}

async function leaveIfSelf(member: TeamMember, destination: string) {
  if (member.userId !== currentUserId.value) return
  await edition.load()
  await router.push(destination)
}

async function onChanged(member: TeamMember, role: EditionRole) {
  toast.success(locale.roleChanged.replace('{nome}', member.name).replace('{papel}', roleLabel(role)))
  if (role !== 'organizador') await leaveIfSelf(member, '/')
}

async function onRemoved(member: TeamMember) {
  toast.success(locale.removed.replace('{nome}', member.name))
  await leaveIfSelf(member, HOME_AFTER_LOGIN)
}
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-col gap-1.5">
      <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
      <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>

    <PhPageSkeleton v-if="state === 'loading'" :kpis="4" :cards="2" :lines="5" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="team.load" />

    <template v-else-if="summary">
      <TeamSummary :summary="summary" />
      <TeamTable
        :members="members"
        :invitations="invitations"
        :current-user-id="currentUserId"
        :read-only="readOnly"
        :busy-id="busyId"
        @invite="inviteOpen = true"
        @edit="edit"
        @resend="resend"
        @cancel="askCancel"
      />
      <RoleMatrix :roles="roles" :permissions="permissions" />
    </template>

    <InviteModal v-model="inviteOpen" :role-options="roleOptions" :submit="team.invite" @sent="toast.success(locale.inviteSent.replace('{email}', $event))" />
    <MemberModal
      v-model="editOpen"
      :member="editing"
      :is-self="editing?.userId === currentUserId"
      :last-organizer="lastOrganizer"
      :role-options="roleOptions"
      :change-role="team.changeRole"
      :remove="team.remove"
      @changed="onChanged"
      @removed="onRemoved"
    />
    <PhModal v-model="cancelOpen" :title="locale.cancelInvitation">
      <p>{{ locale.cancelConfirm.replace('{email}', cancelling?.email ?? '') }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="cancelOpen = false">{{ locale.back }}</PhButton>
        <PhButton variant="danger" :loading="busyId === cancelling?.id" @click="confirmCancel">{{ locale.cancelInvitation }}</PhButton>
      </template>
    </PhModal>
  </section>
</template>
