<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useNewCommunity } from '../composables/useNewCommunity'
import { slugify } from '../composables/format'
import locale from '../locales/pt-br'

const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const form = reactive({ name: '', slug: '' })
const slugEdited = ref(false)
const submitted = ref(false)
const { isSubmitting, errorMessage, fieldErrors, submit } = useNewCommunity()

watch(() => form.name, (name) => {
  if (!slugEdited.value) form.slug = slugify(name)
})

watch(open, (isOpen) => {
  if (isOpen) {
    form.name = ''
    form.slug = ''
    slugEdited.value = false
    submitted.value = false
  }
})

const nameError = computed(() => fieldErrors.value?.name?.[0] ?? (submitted.value && !form.name.trim() ? locale.nameRequired : null))
const slugError = computed(() => fieldErrors.value?.slug?.[0] ?? (submitted.value && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(form.slug) ? locale.slugInvalid : null))

async function onSubmit() {
  submitted.value = true
  if (nameError.value || slugError.value) return

  if (await submit({ name: form.name.trim(), slug: form.slug })) {
    open.value = false
    emit('saved')
  }
}
</script>

<template>
  <PhModal v-model="open" :title="locale.newCommunity">
    <form id="new-community-form" class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
      <PhInput id="community-name" v-model="form.name" :label="locale.communityName" :placeholder="locale.communityNamePlaceholder" :error="nameError" />
      <PhInput id="community-slug" v-model="form.slug" :label="locale.slug" :hint="locale.slugHint" :error="slugError" @update:model-value="slugEdited = true" />
      <div v-if="errorMessage" role="alert" class="alert alert-error alert-soft text-sm">{{ errorMessage }}</div>
    </form>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.cancel }}</PhButton>
      <PhButton type="submit" form="new-community-form" :loading="isSubmitting">{{ locale.create }}</PhButton>
    </template>
  </PhModal>
</template>
