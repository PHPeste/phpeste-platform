<script setup lang="ts">
import { onMounted, ref } from 'vue'
import CommunitiesHeader from '../components/CommunitiesHeader.vue'
import CommunityCard from '../components/CommunityCard.vue'
import NewCommunityModal from '../components/NewCommunityModal.vue'
import NewEditionModal from '../components/NewEditionModal.vue'
import { useCommunities } from '../composables/useCommunities'
import { useOpenEdition } from '../composables/useOpenEdition'
import locale from '../locales/pt-br'
import type { CommunitySummary } from '../types'

const { communities, state, isEmpty, load } = useCommunities()
const { open } = useOpenEdition()

const isCommunityModalOpen = ref(false)
const isEditionModalOpen = ref(false)
const editionCommunity = ref<CommunitySummary | null>(null)

onMounted(load)

function startNewEdition(community: CommunitySummary) {
  editionCommunity.value = community
  isEditionModalOpen.value = true
}

async function onEditionCreated(editionId: string) {
  await open(editionId, true)
}
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-(--ph-bg)">
    <PhStripe />
    <CommunitiesHeader />

    <main id="conteudo" class="mx-auto flex w-full max-w-260 flex-col gap-8 px-5 pt-12 pb-16 md:px-10">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-1.5">
          <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
          <p v-if="state === 'ready' && !isEmpty" class="text-(--ph-ink-2)">
            {{ communities.length === 1 ? locale.oneCommunity : locale.communitiesCount.replace('{n}', String(communities.length)) }}
          </p>
        </div>
        <PhButton @click="isCommunityModalOpen = true">{{ locale.newCommunity }}</PhButton>
      </div>

      <PhPageSkeleton v-if="state === 'loading'" :cards="2" :lines="3" />

      <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="load" />

      <PhCard v-else-if="isEmpty">
        <PhEmptyState :title="locale.emptyTitle" :description="locale.emptyDescription">
          <PhButton @click="isCommunityModalOpen = true">{{ locale.newCommunity }}</PhButton>
        </PhEmptyState>
      </PhCard>

      <CommunityCard
        v-for="community in communities"
        v-else
        :key="community.id"
        :community="community"
        @open="open"
        @new-edition="startNewEdition"
      />
    </main>

    <NewCommunityModal v-model="isCommunityModalOpen" @saved="load" />
    <NewEditionModal v-model="isEditionModalOpen" :community="editionCommunity" @created="onEditionCreated" />
  </div>
</template>
