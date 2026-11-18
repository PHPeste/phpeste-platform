<script setup lang="ts">
import AcceptInvitationForm from '../components/AcceptInvitationForm.vue'
import { HOME_AFTER_LOGIN } from '@/application/shared/layout/redirect'

const route = useRoute()
const token = typeof route.params.token === 'string' ? route.params.token : ''

function onAccepted() {
  navigateTo(HOME_AFTER_LOGIN)
}
</script>

<template>
  <AuthCard>
    <AcceptInvitationForm :token="token" @accepted="onAccepted" />
  </AuthCard>
</template>
