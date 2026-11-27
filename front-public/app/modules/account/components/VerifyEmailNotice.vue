<script setup lang="ts">
import { useEmailVerification } from '../composables/useEmailVerification'
import locale from '../locales/pt-br'

defineProps<{ email: string }>()
const { state, resend } = useEmailVerification()
</script>

<template>
  <div class="alert alert-warning alert-soft flex flex-col items-start gap-2 print:hidden" data-testid="verify-email">
    <b class="font-semibold">{{ locale.verifyTitle }}</b>
    <p class="text-sm">{{ locale.verifyText.replace('{email}', email) }}</p>
    <button type="button" class="btn btn-sm" :disabled="state === 'sending'" @click="resend">{{ locale.verifyResend }}</button>
    <p v-if="state === 'sent' || state === 'error'" role="status" class="text-sm">{{ state === 'sent' ? locale.verifySent : locale.verifyError }}</p>
  </div>
</template>
