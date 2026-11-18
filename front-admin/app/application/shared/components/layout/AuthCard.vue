<template>
  <div class="flex min-h-dvh flex-col bg-(--ph-bg)">
    <PhStripe />
    <main class="flex flex-1 items-center justify-center px-5 py-10">
      <div class="flex w-full max-w-110 flex-col gap-5 rounded-(--radius-box) border border-(--ph-line) bg-(--ph-surface) p-7">
        <div class="flex items-center gap-3">
          <span
            class="flex size-11 items-center justify-center rounded-xl bg-(--ph-accent) font-semibold text-(--ph-ink)"
            aria-hidden="true"
          >Ph</span>
          <b class="font-semibold">PHPeste Platform</b>
        </div>
        <slot />
      </div>
    </main>
  </div>
</template>
