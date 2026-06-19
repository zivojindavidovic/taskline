<template>
  <div
    :class="['card', dragging ? 'dragging' : '', task.completed ? 'completed' : '', dropEdge === 'before' ? 'drop-before' : '', dropEdge === 'after' ? 'drop-after' : '']"
    :draggable="!locked"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
    @dragover="onDragOver"
    @dragleave="onDragLeave"
    @drop="onDrop"
    @click.stop="$emit('open', task.id)"
  >
    <!-- Reorder menu (hover-revealed) -->
    <div v-if="!locked" class="card-menu" @click.stop>
      <DropdownMenu align="right" :width="150">
        <template #trigger>
          <button type="button" class="btn ghost icon-only sm card-menu-btn" title="Reorder task" aria-label="Reorder task">
            <MoreIcon class="w-4 h-4" />
          </button>
        </template>
        <div class="py-1">
          <MenuItem :disabled="index === 0" @click="index > 0 && $emit('move', task.id, -1)">
            <ArrowUpIcon class="w-3.5 h-3.5" /> Move up
          </MenuItem>
          <MenuItem :disabled="index === count - 1" @click="index < count - 1 && $emit('move', task.id, 1)">
            <ArrowDownIcon class="w-3.5 h-3.5" /> Move down
          </MenuItem>
        </div>
      </DropdownMenu>
    </div>

    <!-- ID row -->
    <div class="card-id-row">
      <span>{{ task.key }}</span>
      <PriorityBadge :priority="task.priority" />
    </div>

    <!-- Title -->
    <div class="card-title">{{ task.title }}</div>

    <!-- Tags -->
    <div v-if="task.tags?.length" class="tags">
      <span v-for="tag in task.tags" :key="tag" class="tag">{{ tag }}</span>
    </div>

    <!-- Meta row -->
    <div class="card-meta">
      <span v-if="task.due_date" :class="['chip', dueInfo.urgent ? 'urgent' : '']">
        <CalendarIcon class="w-4 h-4" />
        {{ dueInfo.label }}
      </span>
      <span v-if="task.comments?.length" class="chip">
        <CommentIcon class="w-4 h-4" />{{ task.comments.length }}
      </span>
      <span v-if="task.subtasks?.length" :class="['chip', subtasksDone === task.subtasks.length ? 'done-chip' : '']">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        {{ subtasksDone }}/{{ task.subtasks.length }}
      </span>
      <span class="spacer" />
      <template v-if="task.assignees?.length">
        <span class="assignee-stack">
          <span v-for="u in task.assignees.slice(0, 3)" :key="u.id" class="stack-item">
            <Avatar :name="u.name" size="sm" />
          </span>
          <span v-if="task.assignees.length > 3" class="avatar-more">+{{ task.assignees.length - 3 }}</span>
        </span>
      </template>
      <Avatar v-else-if="task.assignee" :name="task.assignee.name" size="sm" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Avatar from '@/Components/UI/Avatar.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import MenuItem from '@/Components/UI/MenuItem.vue'
import { CalendarIcon, CommentIcon, MoreIcon, ArrowUpIcon, ArrowDownIcon } from '@/Components/UI/Icons.vue'
import { formatDueDate } from '@/utils/dueDate'

const props = defineProps({
  task:     { type: Object, required: true },
  dragging: { type: Boolean, default: false },
  locked:   { type: Boolean, default: false },
  index:    { type: Number, default: 0 },
  count:    { type: Number, default: 1 },
})
const emit = defineEmits(['open', 'dragStart', 'dragEnd', 'card-drop', 'move'])

// Which edge the hovering card would drop against — drives the accent line.
const dropEdge = ref(null)

function onDragStart(e) {
  if (props.locked) { e.preventDefault(); return }
  e.dataTransfer.effectAllowed = 'move'
  emit('dragStart', props.task.id)
}

function onDragEnd() {
  dropEdge.value = null
  emit('dragEnd')
}

function onDragOver(e) {
  // Don't treat the card being dragged as its own drop target.
  if (props.locked || props.dragging) return
  e.preventDefault()
  e.stopPropagation()
  const r = e.currentTarget.getBoundingClientRect()
  dropEdge.value = e.clientY - r.top < r.height / 2 ? 'before' : 'after'
}

function onDragLeave(e) {
  if (!e.currentTarget.contains(e.relatedTarget)) dropEdge.value = null
}

function onDrop(e) {
  if (props.locked || props.dragging) return
  e.preventDefault()
  e.stopPropagation() // beat the column-body drop, which would just append
  const place = dropEdge.value || 'before'
  dropEdge.value = null
  emit('card-drop', props.task.id, place)
}

const subtasksDone = computed(() => props.task.subtasks?.filter(s => s.completed).length ?? 0)

const dueInfo = computed(() =>
  formatDueDate(props.task.due_date, props.task.start_date, props.task.completed)
)
</script>

<style scoped>
.chip.done-chip {
  background: color-mix(in oklab, var(--status-done) 15%, var(--bg-card, var(--bg-panel)));
  color: var(--status-done);
  border-color: color-mix(in oklab, var(--status-done) 30%, var(--border));
}
.assignee-stack { display: inline-flex; align-items: center; }
.stack-item + .stack-item { margin-left: -6px; }
.stack-item :deep(div) { box-shadow: 0 0 0 2px var(--bg-card, var(--bg-panel)); border-radius: 50%; }
.avatar-more {
  display: inline-flex; align-items: center; justify-content: center;
  width: 24px; height: 24px; border-radius: 50%;
  background: var(--bg-active); color: var(--fg-muted);
  font-size: 10px; font-weight: 600;
  box-shadow: 0 0 0 2px var(--bg-card, var(--bg-panel));
  margin-left: -6px;
}
</style>
