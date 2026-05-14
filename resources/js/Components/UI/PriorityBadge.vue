<template>
  <span class="inline-flex items-center gap-1.5">
    <span
      class="w-2 h-2 rounded-sm shrink-0"
      :style="{ background: color }"
    />
    <span v-if="showLabel" class="text-sm" style="color:var(--fg)">{{ label }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  priority:  { type: String, default: 'med' },
  showLabel: { type: Boolean, default: false },
})

const PRIORITIES = {
  urgent: { label: 'Urgent', color: '#dc2626' },
  high:   { label: 'High',   color: '#d97706' },
  med:    { label: 'Medium', color: '#94948c' },
  low:    { label: 'Low',    color: '#6b7280' },
}

const color = computed(() => PRIORITIES[props.priority]?.color ?? '#94948c')
const label = computed(() => PRIORITIES[props.priority]?.label ?? 'Medium')
</script>
