<script setup lang="ts">
import { computed } from 'vue'
import { NAV_GROUPS, type NavItem } from '@/application/shared/layout/navigation'
import { usePermissions } from '@/application/shared/composables/usePermissions'
import { useEdition } from '@/application/shared/composables/useEdition'
import locale from '@/modules/shell/locales/pt-br'

const emit = defineEmits<{ navigate: [] }>()

const route = useRoute()
const { visibleItems } = usePermissions()
const { current } = useEdition()

const groups = computed(() =>
  NAV_GROUPS.map((group) => ({ group, items: visibleItems.value.filter((i) => i.group === group) })).filter((g) => g.items.length > 0),
)

// O item mais específico vence: /cfp/avaliacao não acende "Configurar CFP".
function isActive(item: NavItem): boolean {
  const path = route.path.replace(/\/+$/, '') || '/'
  if (item.to === '/') return path === '/'
  if (!(path === item.to || path.startsWith(`${item.to}/`))) return false
  return !visibleItems.value.some((o) => o.to.length > item.to.length && (path === o.to || path.startsWith(`${o.to}/`)))
}
</script>

<template>
  <div class="ph-side flex min-h-full w-full flex-col">
    <div class="flex items-center gap-3 px-5 pb-2 pt-6">
      <div class="flex size-10 items-center justify-center rounded-[10px] bg-(--ph-accent) text-[15px] font-semibold text-(--ph-ink)" aria-hidden="true">PH</div>
      <div class="flex flex-col leading-tight">
        <span class="text-[17px] font-semibold text-(--ph-on-brand)">{{ locale.brandName }}</span>
        <span class="text-xs text-(--ph-side-muted)">{{ locale.brandSubtitle }}</span>
      </div>
    </div>

    <nav :aria-label="locale.mainNav" class="flex-1 px-3">
      <template v-for="g in groups" :key="g.group">
        <h2 class="mx-3.5 mb-1.5 mt-5 text-[11px] font-medium uppercase tracking-[.09em] text-(--ph-side-muted)">{{ locale.groups[g.group] }}</h2>
        <ul class="flex flex-col gap-0.5">
          <li v-for="item in g.items" :key="item.key">
            <NuxtLink
              :to="item.to"
              class="ph-nav"
              :class="{ 'is-active': isActive(item) }"
              :aria-current="isActive(item) ? 'page' : undefined"
              @click="emit('navigate')"
            >
              <component :is="item.icon" class="size-5 shrink-0" :stroke-width="1.75" aria-hidden="true" />
              {{ locale.nav[item.key] }}
            </NuxtLink>
          </li>
        </ul>
      </template>
    </nav>

    <p class="px-5 pb-4 pt-5 text-xs text-(--ph-side-muted)">
      {{ locale.activeEdition }}<br>
      <span class="font-medium text-(--ph-side-text)">{{ current ? `${current.name} · ${current.city}` : locale.noEdition }}</span>
    </p>
    <PhStripe />
  </div>
</template>

<style scoped>
.ph-side { background: var(--ph-ink); color: var(--ph-side-text); }
.ph-nav {
  display: flex; align-items: center; gap: 12px; min-height: 44px; padding: 0 14px;
  border-radius: 10px; color: var(--ph-side-text); text-decoration: none; font-size: 14px; font-weight: 500;
}
.ph-nav:hover { background: color-mix(in srgb, var(--ph-side-text) 10%, transparent); color: var(--ph-on-brand); }
.ph-nav.is-active { background: var(--ph-accent); color: var(--ph-ink); }
.ph-nav:focus-visible { outline-color: var(--ph-accent); }
</style>
