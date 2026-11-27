<script setup lang="ts">
import { computed, ref } from 'vue'
import InstallmentsSelect from './InstallmentsSelect.vue'
import { evenInstallments } from '../composables/installments'
import locale from '../locales/pt-br'

const props = defineProps<{ totalCents: number, maxInstallments: number, busy: boolean }>()
defineEmits<{ pay: [installments: number] }>()
const installments = ref(1)
const options = computed(() => evenInstallments(props.totalCents, props.maxInstallments))
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="hosted-card">
    <p class="text-sm text-(--ph-ink-2)">{{ locale.hostedCard }}</p>
    <InstallmentsSelect id="hosted-installments" v-model="installments" :options="options" :note="maxInstallments > 1" />
    <button type="button" class="btn btn-primary w-full" :disabled="busy" :aria-busy="busy ? 'true' : undefined" @click="$emit('pay', installments)">
      <span v-if="busy" class="loading loading-spinner loading-sm" aria-hidden="true" />
      {{ locale.payCardHosted }}
    </button>
  </div>
</template>
