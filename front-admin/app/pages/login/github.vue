<script setup lang="ts">
import GithubCallbackView from '~/modules/githubLogin/views/GithubCallbackView.vue'

definePageMeta({ layout: 'blank', auth: false })
</script>

<template>
  <GithubCallbackView />
</template>
