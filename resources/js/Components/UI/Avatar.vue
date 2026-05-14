<template>
  <div
    :class="sizeClass"
    class="rounded-full flex items-center justify-center font-semibold shrink-0 select-none"
    :style="{ background: bgColor, color: fgColor }"
    :title="name"
  >
    {{ initials }}
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  name:  { type: String, default: null },
  email: { type: String, default: null },
  size:  { type: String, default: 'sm' },   // sm | md | lg
  color: { type: String, default: null },   // explicit bg color override
})

// Derive initials from name
const initials = computed(() => {
  if (!props.name) return '?'
  const parts = props.name.trim().split(/\s+/)
  if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
  return props.name.slice(0, 2).toUpperCase()
})

// Deterministic color from name
const COLORS = [
  ['#ddd6fe', '#5b21b6'], // purple
  ['#bfdbfe', '#1d4ed8'], // blue
  ['#bbf7d0', '#15803d'], // green
  ['#fed7aa', '#c2410c'], // orange
  ['#fecaca', '#b91c1c'], // red
  ['#e0e7ff', '#3730a3'], // indigo
  ['#cffafe', '#0e7490'], // cyan
]

function hashStr(s) {
  let h = 0
  for (let i = 0; i < s.length; i++) h = (Math.imul(31, h) + s.charCodeAt(i)) | 0
  return Math.abs(h)
}

const bgColor = computed(() => {
  if (props.color) return props.color
  const key = props.name ?? props.email ?? '?'
  return COLORS[hashStr(key) % COLORS.length][0]
})

const fgColor = computed(() => {
  if (props.color) return '#ffffff'
  const key = props.name ?? props.email ?? '?'
  return COLORS[hashStr(key) % COLORS.length][1]
})

const sizeClass = computed(() => ({
  'w-6 h-6 text-[11px]': props.size === 'sm',
  'w-8 h-8 text-xs':     props.size === 'md',
  'w-10 h-10 text-sm':   props.size === 'lg',
}))
</script>
