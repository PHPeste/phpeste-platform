<script setup lang="ts">
import locale from '../locales/pt-br'
import { shortDate } from '../composables/format'
import type { TeamInvitation, TeamMember } from '../types'

defineProps<{ members: TeamMember[], invitations: TeamInvitation[], currentUserId: string | null, readOnly: boolean, busyId: string | null }>()
const emit = defineEmits<{ invite: [], edit: [member: TeamMember], resend: [invitation: TeamInvitation], cancel: [invitation: TeamInvitation] }>()
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">{{ locale.tableTitle }}</h2>
        <PhButton v-if="!readOnly" @click="emit('invite')">{{ locale.invite }}</PhButton>
      </div>
      <PhTable :caption="locale.tableTitle">
        <thead>
          <tr>
            <th scope="col">{{ locale.person }}</th>
            <th scope="col">{{ locale.role }}</th>
            <th scope="col">{{ locale.access }}</th>
            <th scope="col">{{ locale.lastSeen }}</th>
            <th scope="col"><span class="sr-only">{{ locale.actions }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="member in members" :key="member.id">
            <td>
              <div class="flex items-center gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-(--ph-accent) text-xs font-semibold text-(--ph-ink)" aria-hidden="true">{{ member.initials }}</span>
                <div class="flex min-w-0 flex-col">
                  <b class="font-medium">{{ member.name }} <span v-if="member.userId === currentUserId" class="font-normal text-(--ph-ink-3)">{{ locale.you }}</span></b>
                  <span class="truncate text-xs text-(--ph-ink-3)">{{ member.email }}</span>
                </div>
              </div>
            </td>
            <td><PhBadge :status="member.role === 'organizador' ? 'info' : 'neutral'">{{ member.roleLabel }}</PhBadge></td>
            <td>{{ member.access }}</td>
            <td class="whitespace-nowrap">{{ member.lastSeen }}</td>
            <td class="text-right">
              <PhButton v-if="!readOnly" variant="ghost" :aria-label="`${locale.edit} ${member.name}`" @click="emit('edit', member)">{{ locale.edit }}</PhButton>
            </td>
          </tr>
          <tr v-for="invitation in invitations" :key="invitation.id">
            <td>
              <div class="flex items-center gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full border border-dashed border-(--ph-line) text-xs font-semibold text-(--ph-ink-3)" aria-hidden="true">{{ invitation.initials }}</span>
                <div class="flex min-w-0 flex-col">
                  <b class="font-medium">{{ invitation.name ?? invitation.email }}</b>
                  <span class="truncate text-xs text-(--ph-ink-3)">{{ invitation.email }}</span>
                </div>
              </div>
            </td>
            <td><PhBadge>{{ invitation.roleLabel }}</PhBadge></td>
            <td>
              <PhBadge status="warn">{{ locale.pendingInvitation }}</PhBadge>
              <span class="ml-2 text-xs text-(--ph-ink-3)">{{ locale.expiresOn.replace('{data}', shortDate(invitation.expiresAt)) }}</span>
            </td>
            <td>{{ locale.never }}</td>
            <td class="text-right">
              <div v-if="!readOnly" class="flex justify-end gap-2">
                <PhButton variant="ghost" :loading="busyId === invitation.id" :aria-label="`${locale.resend} ${invitation.email}`" @click="emit('resend', invitation)">{{ locale.resend }}</PhButton>
                <PhButton variant="ghost" :aria-label="`${locale.cancelInvitation} ${invitation.email}`" @click="emit('cancel', invitation)">{{ locale.cancelInvitation }}</PhButton>
              </div>
            </td>
          </tr>
        </tbody>
      </PhTable>
    </div>
  </PhCard>
</template>
