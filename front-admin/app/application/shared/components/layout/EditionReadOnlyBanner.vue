<script setup lang="ts">
import { useEdition } from '@/application/shared/composables/useEdition'
import locale from '@/modules/shell/locales/pt-br'

const { isReadOnly } = useEdition()
</script>

<template>
  <div v-if="isReadOnly" role="status" class="alert alert-info alert-soft mb-6 text-sm">
    <span><b class="font-semibold">{{ locale.readOnlyTitle }}.</b> {{ locale.readOnly }}</span>
  </div>
</template>
