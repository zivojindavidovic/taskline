<template>
  <div
    :class="['rounded-lg border cursor-pointer flex flex-col gap-1.5 transition-all', dragging ? 'card-dragging' : '']"
    :style="{
      background: 'var(--bg-panel)',
      borderColor: 'var(--border)',
      padding: '12px',
      boxShadow: 'var(--shadow-sm)',
    }"
    draggable="true"
    @dragstart="onDragStart"
    @dragend="$emit('dragEnd')"
    @click.stop="$emit('open', task.id)"
  >
    <!-- ID row -->
    <div class="flex items-center gap-2">
      <span class="text-xs font-mono" style="color:var(--fg-subtle)">{{ task.key }}</span>
      <PriorityBadge :priority="task.priority" />
      <span
        v-if="task.completed"
        class="ml-auto inline-flex items-center gap-1 text-[11px] font-medium"
        style="color:var(--status-done)"
      >
        <CheckIcon class="w-3 h-3" /> Completed
      </span>
    </div>

    <!-- Title -->
    <div
      class="text-sm font-medium leading-snug"
      :class="task.completed ? 'line-through' : ''"
      :style="task.completed ? 'color:var(--fg-muted)' : 'color:var(--fg)'"
    >{{ task.title }}</div>

    <!-- Tags -->
    <div v-if="task.tags?.length" class="flex flex-wrap gap-1">
      <span
        v-for="tag in task.tags"
        :key="tag"
        class="text-[11px] px-1.5 py-0.5 rounded border"
        style="background:var(--bg-sunken);color:var(--fg-muted);border-color:var(--border)"
      >{{ tag }}</span>
    </div>

    <!-- Meta row -->
    <div class="flex items-center gap-2 mt-0.5">
      <span
        v-if="task.due_date"
        class="inline-flex items-center gap-1 text-xs"
        style="color:var(--fg-muted)"
      >
        <CalendarIcon class="w-3 h-3" />
        {{ formatDate(task.start_date, task.due_date) }}
      </span>
      <span
        v-if="task.comments?.length"
        class="inline-flex items-center gap-1 text-xs"
        style="color:var(--fg-muted)"
      >
        <CommentIcon class="w-3 h-3" />{{ task.comments.length }}
      </span>
      <span class="flex-1" />
      <Avatar v-if="task.assignee" :name="task.assignee.name" size="sm" />
    </div>
  </div>
</template>

<script setup>
import Avatar from '@/Components/UI/Avatar.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import { CheckIcon, CalendarIcon, CommentIcon } from '@/Components/UI/Icons.vue'

const props = defineProps({
  task:     { type: Object, required: true },
  dragging: { type: Boolean, default: false },
})
const emit = defineEmits(['open', 'dragStart', 'dragEnd'])

function onDragStart(e) {
  e.dataTransfer.effectAllowed = 'move'
  emit('dragStart', props.task.id)
}

function formatDate(start, due) {
  if (!due) return ''
  if (start && start !== due) return `${fmtD(start)}–${fmtD(due)}`
  return fmtD(due)
}

function fmtD(d) {
  if (!d) return ''
  const date = new Date(d)
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}
</script>
