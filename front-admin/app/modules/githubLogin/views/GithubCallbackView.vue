<script setup lang="ts">
import { computed } from 'vue'
import GithubCallbackStatus from '../components/GithubCallbackStatus.vue'
import { HOME_AFTER_LOGIN } from '@/application/shared/layout/redirect'

const route = useRoute()

const text = (value: unknown) => (typeof value === 'string' && value !== '' ? value : null)
const code = computed(() => text(route.query.code))
const state = computed(() => text(route.query.state))
const providerError = computed(() => text(route.query.error))

function onSuccess() {
  navigateTo(HOME_AFTER_LOGIN)
}
</script>

<template>
  <AuthCard>
    <GithubCallbackStatus :code="code" :state="state" :provider-error="providerError" @success="onSuccess" />
  </AuthCard>
</template>
