<template>
  <Teleport to="body">
    <div class="fixed bottom-5 right-5 z-[200] flex flex-col gap-2 pointer-events-none">
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts"
          :key="t.id"
          class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm pointer-events-auto animate-toast-in"
          style="
            background: var(--fg);
            color: var(--bg-panel);
            box-shadow: var(--shadow-lg);
            max-width: 360px;
          "
        >
          <span class="flex-1">{{ t.message }}</span>
          <button
            v-if="t.undo"
            class="font-semibold text-xs cursor-pointer ml-2"
            style="color: var(--accent)"
            @click="undo(t)"
          >
            Undo
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { useToast } from '@/composables/useToast'

const { toasts, undo } = useToast()
</script>

<style scoped>
.toast-enter-active { transition: all 200ms ease; }
.toast-leave-active { transition: all 150ms ease; }
.toast-enter-from   { opacity: 0; transform: translateY(16px); }
.toast-leave-to     { opacity: 0; transform: translateY(8px); }
</style>
