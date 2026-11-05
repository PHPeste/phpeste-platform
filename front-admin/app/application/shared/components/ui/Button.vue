<script setup lang="ts">
import { computed, resolveComponent } from 'vue'
import locale from '@/application/locales/pt-br/ui'

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger'

const props = withDefaults(
  defineProps<{
    variant?: Variant
    type?: 'button' | 'submit' | 'reset'
    loading?: boolean
    disabled?: boolean
    block?: boolean
    to?: string
  }>(),
  { variant: 'primary', type: 'button', loading: false, disabled: false, block: false, to: undefined },
)

const classes = computed(() => [
  'btn',
  {
    primary: 'btn-primary',
    secondary: 'btn-neutral btn-soft border-base-300',
    ghost: 'btn-ghost border-base-300',
    danger: 'btn-error',
  }[props.variant],
  { 'w-full': props.block },
])

const isLink = computed(() => !!props.to)
const tag = computed(() => (isLink.value ? resolveComponent('NuxtLink') : 'button'))
const blocked = computed(() => props.disabled || props.loading)
</script>

<template>
  <component
    :is="tag"
    :to="isLink ? to : undefined"
    :type="isLink ? undefined : type"
    :class="classes"
    :disabled="isLink ? undefined : blocked"
    :aria-disabled="isLink && blocked ? 'true' : undefined"
    :aria-busy="loading ? 'true' : undefined"
  >
    <span v-if="loading" class="loading loading-spinner loading-sm" aria-hidden="true" />
    <span v-if="loading" class="sr-only">{{ locale.loading }}</span>
    <slot />
  </component>
</template>
