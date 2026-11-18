<script setup lang="ts">
import locale from '../locales/pt-br'
import type { PermissionInfo, RoleInfo } from '../types'

defineProps<{ roles: RoleInfo[], permissions: PermissionInfo[] }>()
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-3">
      <h2 class="text-lg font-semibold">{{ locale.matrixTitle }}</h2>
      <PhTable :caption="locale.matrixTitle">
        <thead>
          <tr>
            <th scope="col">{{ locale.permission }}</th>
            <th v-for="role in roles" :key="role.id" scope="col" class="text-center">{{ role.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="permission in permissions" :key="permission.id">
            <th scope="row" class="font-normal">{{ permission.label }}</th>
            <td v-for="role in roles" :key="role.id" class="text-center">
              <span v-if="role.permissions.includes(permission.id)" class="font-semibold text-(--ph-ok)" aria-hidden="true">✓</span>
              <span v-else class="text-(--ph-ink-3)" aria-hidden="true">—</span>
              <span class="sr-only">{{ role.permissions.includes(permission.id) ? locale.allowed : locale.denied }}</span>
            </td>
          </tr>
        </tbody>
      </PhTable>
    </div>
  </PhCard>
</template>
