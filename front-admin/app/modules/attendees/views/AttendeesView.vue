<script setup lang="ts">
import { computed } from 'vue'
import { useEdition } from '@/application/shared/composables/useEdition'
import AttendeesPanel from '../components/AttendeesPanel.vue'
import locale from '../locales/pt-br'

const { current, isReadOnly, role } = useEdition()
// A lista é da organização. Check-in e voluntário chegam à mesma rota pelo menu,
// mas a API não lhes dá a lista: ficam com o aviso até a tela de check-in.
const canView = computed(() => role.value === 'organizador')
const canManage = computed(() => role.value === 'organizador')
</script>

<template>
  <AttendeesPanel v-if="current && canView" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" :can-manage="canManage" />
  <PhEmptyState v-else-if="current" :title="locale.noAccess" :description="locale.noAccessHint" />
</template>
