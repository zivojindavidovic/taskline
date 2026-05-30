<template>
  <div ref="container" class="relative inline-flex">
    <div ref="trigger" @click.stop="toggle">
      <slot name="trigger" />
    </div>

    <Teleport to="body">
      <div
        v-if="open"
        ref="menu"
        class="fixed min-w-[180px]"
        :style="menuStyle"
        @click="onInnerClick"
      >
        <slot :close="close" />
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, nextTick, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  align: { type: String, default: 'left' },
  width: { type: Number, default: null },
})

const open = ref(false)
const container = ref(null)
const trigger = ref(null)
const menu = ref(null)
const menuStyle = ref({})

const GAP = 4

function buildStyle() {
  const el = trigger.value
  if (!el) return
  const r = el.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight

  // Measure the menu height if it's already rendered, else estimate.
  const menuH = menu.value ? menu.value.offsetHeight : 0
  const spaceBelow = vh - r.bottom
  // Flip upward only when there isn't room below but there is above.
  const flipUp = menuH > 0 && spaceBelow < menuH + GAP && r.top > spaceBelow

  const style = {
    background: 'var(--bg-panel)',
    border: '1px solid var(--border)',
    borderRadius: 'var(--r-md)',
    boxShadow: 'var(--shadow-lg)',
    padding: '4px',
    // Must sit above every fixed layer in the app (task panel 51, participants
    // modal 200/201, subtask panel 300). Inline so it never depends on Tailwind.
    zIndex: 1000,
  }
  if (props.width) style.width = props.width + 'px'

  if (props.align === 'right') {
    style.right = (vw - r.right) + 'px'
  } else {
    style.left = r.left + 'px'
  }

  if (flipUp) {
    style.bottom = (vh - r.top + GAP) + 'px'
  } else {
    style.top = (r.bottom + GAP) + 'px'
  }

  menuStyle.value = style
}

async function updatePosition() {
  buildStyle()        // first pass: anchor without height knowledge
  await nextTick()
  buildStyle()        // second pass: now the menu is measured → flip if needed
}

function toggle() {
  open.value = !open.value
  if (open.value) updatePosition()
}
function close() { open.value = false }

function onInnerClick(e) {
  e.stopPropagation()
  // Close when a button (menu item) is clicked, unless it or an ancestor is marked keep-open
  const btn = e.target.closest('button')
  if (btn && !e.target.closest('[data-keep-open]')) {
    open.value = false
  }
}

function onOutsideClick(e) {
  const inTrigger = container.value && container.value.contains(e.target)
  const inMenu = menu.value && menu.value.contains(e.target)
  if (!inTrigger && !inMenu) open.value = false
}

function onKey(e) {
  if (e.key === 'Escape') open.value = false
}

function onReflow() {
  if (open.value) updatePosition()
}

onMounted(() => {
  document.addEventListener('mousedown', onOutsideClick)
  document.addEventListener('keydown', onKey)
  window.addEventListener('scroll', onReflow, true)
  window.addEventListener('resize', onReflow)
})
onUnmounted(() => {
  document.removeEventListener('mousedown', onOutsideClick)
  document.removeEventListener('keydown', onKey)
  window.removeEventListener('scroll', onReflow, true)
  window.removeEventListener('resize', onReflow)
})
</script>
