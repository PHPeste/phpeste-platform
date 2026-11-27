<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import OrderCard from './OrderCard.vue'
import VerifyEmailNotice from './VerifyEmailNotice.vue'
import { useMyOrders } from '../composables/useMyOrders'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string }>()
const auth = useAuthStore()
const { orders, state, emailVerified, load } = useMyOrders(props.editionId)

onMounted(load)
watch(() => auth.isAuthenticated, (value) => { if (value) load() })
</script>

<template>
  <div class="flex flex-col gap-4">
    <div v-if="state === 'loading' || state === 'idle'" class="flex flex-col gap-3" role="status" aria-busy="true">
      <div v-for="n in 2" :key="n" class="skeleton h-32 w-full" />
    </div>
    <div v-else-if="state === 'error'" role="alert" class="alert alert-error alert-soft flex flex-wrap justify-between gap-3">
      <span>{{ locale.loadError }}</span>
      <button type="button" class="btn btn-sm" @click="load">{{ locale.retry }}</button>
    </div>
    <template v-else>
      <VerifyEmailNotice v-if="!emailVerified && auth.user" :email="auth.user.email" />
      <p v-if="!orders.length" class="card border border-(--ph-line) bg-(--ph-surface) p-6">{{ locale.noOrders }}</p>
      <OrderCard v-for="order in orders" :key="order.id" :order="order" />
    </template>
  </div>
</template>
