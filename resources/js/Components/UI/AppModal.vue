<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="show"
        class="fixed inset-0 z-60 flex items-center justify-center p-5 animate-fade-in"
        style="background:rgba(0,0,0,0.25)"
        @click.self="$emit('close')"
      >
        <div
          class="flex flex-col w-full animate-scale-in rounded-xl"
          :style="{
            maxWidth: large ? '560px' : '480px',
            maxHeight: '90vh',
            background: 'var(--bg-panel)',
            border: '1px solid var(--border)',
            boxShadow: 'var(--shadow-lg)',
          }"
          @click.stop
        >
          <!-- Header -->
          <div
            class="flex items-center gap-2 px-5 py-4 border-b"
            style="border-color:var(--border)"
          >
            <span class="flex-1 text-[15px] font-semibold" style="color:var(--fg)">{{ title }}</span>
            <button
              type="button"
              class="p-1 rounded hover:bg-[var(--bg-hover)] transition-colors"
              style="color:var(--fg-muted)"
              @click="$emit('close')"
            >
              <CloseIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto px-5 py-5 flex flex-col gap-4">
            <slot />
          </div>

          <!-- Footer -->
          <div
            v-if="$slots.footer"
            class="flex items-center justify-end gap-2 px-5 py-3 border-t rounded-b-xl"
            style="border-color:var(--border);background:var(--bg-sunken)"
          >
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import { CloseIcon } from '@/Components/UI/Icons.vue'

const props = defineProps({
  show:  { type: Boolean, required: true },
  title: { type: String,  default: '' },
  large: { type: Boolean, default: false },
})
defineEmits(['close'])

function onKey(e) {
  if (e.key === 'Escape' && props.show) {
    // parent handles close via show prop
  }
}
onMounted(()   => document.addEventListener('keydown', onKey))
onUnmounted(() => document.removeEventListener('keydown', onKey))
</script>

<style>
.z-60 { z-index: 60; }
</style>
