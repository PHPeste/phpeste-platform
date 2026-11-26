<script setup lang="ts">
import { computed } from 'vue'
import CheckoutField from './CheckoutField.vue'
import ErrorSummary from './ErrorSummary.vue'
import QuestionField from './QuestionField.vue'
import { fieldId } from '../composables/useCheckout'
import { maskCpf } from '../composables/format'
import locale from '../locales/pt-br'
import type { AttendeeForm, BuyerForm, SelectedLine } from '../types'

const props = defineProps<{ lines: SelectedLine[], attendees: Record<string, AttendeeForm[]>, errors: Record<string, string>, requiresPayment: boolean }>()
const buyer = defineModel<BuyerForm>('buyer', { required: true })
defineEmits<{ copy: [ticketId: string, index: number] }>()

const cpf = computed({ get: () => buyer.value.document, set: (value: string) => { buyer.value.document = maskCpf(value) } })

const blocks = computed(() => {
  let number = 0
  return props.lines.flatMap((line, itemIndex) => (props.attendees[line.type.id] ?? []).map((attendee, position) => {
    number++
    return { line, itemIndex, position, attendee, number, prefix: `items.${itemIndex}.attendees.${position}` }
  }))
})

const labels = computed(() => {
  const map: Record<string, string> = { 'buyer.name': `${locale.buyerTitle}: ${locale.name}`, 'buyer.email': `${locale.buyerTitle}: ${locale.email}`, 'buyer.document': `${locale.buyerTitle}: ${locale.cpf}` }
  for (const block of blocks.value) {
    const title = locale.attendee.replace('{n}', String(block.number)).replace('{tipo}', block.line.type.name)
    map[`${block.prefix}.name`] = `${title}: ${locale.name}`
    map[`${block.prefix}.email`] = `${title}: ${locale.email}`
    for (const question of block.line.type.questions) map[`${block.prefix}.answers.${question.id}`] = `${title}: ${question.label}`
  }
  return map
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <ErrorSummary :errors="errors" :labels="labels" :field-id="fieldId" />

    <fieldset class="flex flex-col gap-4 rounded-box border border-base-300 bg-base-100 p-4">
      <legend class="px-1 text-lg font-semibold">{{ locale.buyerTitle }}</legend>
      <p class="text-sm text-(--ph-ink-2)">{{ locale.buyerHint }}</p>
      <CheckoutField :id="fieldId('buyer.name')" v-model="buyer.name" :label="locale.name" autocomplete="name" required :error="errors['buyer.name']" />
      <CheckoutField :id="fieldId('buyer.email')" v-model="buyer.email" :label="locale.email" type="email" inputmode="email" autocomplete="email" required :error="errors['buyer.email']" />
      <CheckoutField :id="fieldId('buyer.document')" v-model="cpf" :label="locale.cpf" inputmode="numeric" autocomplete="off" :hint="requiresPayment ? locale.cpfHint : undefined" :required="requiresPayment" :error="errors['buyer.document']" />
    </fieldset>

    <section class="flex flex-col gap-4" aria-labelledby="attendees-title">
      <div class="flex flex-col gap-1">
        <h2 id="attendees-title" class="text-lg font-semibold">{{ locale.attendeesTitle }}</h2>
        <p class="text-sm text-(--ph-ink-2)">{{ locale.attendeesHint }}</p>
      </div>
      <fieldset v-for="block in blocks" :key="block.prefix" class="flex flex-col gap-4 rounded-box border border-base-300 bg-base-100 p-4" :data-testid="`attendee-${block.number}`">
        <legend class="px-1 font-semibold">{{ locale.attendee.replace('{n}', String(block.number)).replace('{tipo}', block.line.type.name) }}</legend>
        <button type="button" class="btn btn-ghost btn-sm self-start" @click="$emit('copy', block.line.type.id, block.position)">{{ locale.useBuyer }}</button>
        <CheckoutField :id="fieldId(`${block.prefix}.name`)" v-model="block.attendee.name" :label="locale.name" required :error="errors[`${block.prefix}.name`]" />
        <CheckoutField :id="fieldId(`${block.prefix}.email`)" v-model="block.attendee.email" :label="locale.email" type="email" inputmode="email" required :error="errors[`${block.prefix}.email`]" />
        <QuestionField
          v-for="question in block.line.type.questions"
          :id="fieldId(`${block.prefix}.answers.${question.id}`)"
          :key="question.id"
          v-model="block.attendee.answers[question.id]"
          :question="question"
          :error="errors[`${block.prefix}.answers.${question.id}`] ?? null"
        />
      </fieldset>
    </section>
  </div>
</template>
