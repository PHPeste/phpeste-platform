<script setup lang="ts">
import ResetPasswordForm from '../components/ResetPasswordForm.vue'

const route = useRoute()
const text = (value: unknown) => (typeof value === 'string' && value !== '' ? value : null)
</script>

<template>
  <AuthCard>
    <ResetPasswordForm :email="text(route.query.email)" :token="text(route.query.token)" />
  </AuthCard>
</template>
