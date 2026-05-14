<template>
  <div ref="container" class="relative inline-flex">
    <div @click.stop="toggle">
      <slot name="trigger" />
    </div>

    <Teleport to="body">
      <div
        v-if="open"
        class="fixed inset-0 z-50"
        style="pointer-events:none"
      />
    </Teleport>

    <div
      v-if="open"
      class="absolute z-50 rounded-lg py-1 min-w-[180px]"
      :style="{
        top: 'calc(100% + 4px)',
        [align === 'right' ? 'right' : 'left']: 0,
        width: width ? width + 'px' : undefined,
        background: 'var(--bg-panel)',
        border: '1px solid var(--border)',
        boxShadow: 'var(--shadow-lg)',
      }"
      @click.stop
    >
      <slot :close="close" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  align: { type: String, default: 'left' },
  width: { type: Number, default: null },
})

const open = ref(false)
const container = ref(null)

function toggle() { open.value = !open.value }
function close()  { open.value = false }

function onOutsideClick(e) {
  if (container.value && !container.value.contains(e.target)) {
    open.value = false
  }
}

function onKey(e) {
  if (e.key === 'Escape') open.value = false
}

onMounted(() => {
  document.addEventListener('mousedown', onOutsideClick)
  document.addEventListener('keydown', onKey)
})
onUnmounted(() => {
  document.removeEventListener('mousedown', onOutsideClick)
  document.removeEventListener('keydown', onKey)
})
</script>
