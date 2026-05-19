<template>
  <div
    :class="['card', dragging ? 'dragging' : '', task.completed ? 'completed' : '']"
    draggable="true"
    @dragstart="onDragStart"
    @dragend="$emit('dragEnd')"
    @click.stop="$emit('open', task.id)"
  >
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
import { computed } from 'vue'
import Avatar from '@/Components/UI/Avatar.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import { CalendarIcon, CommentIcon } from '@/Components/UI/Icons.vue'
import { formatDueDate } from '@/utils/dueDate'

const props = defineProps({
  task:     { type: Object, required: true },
  dragging: { type: Boolean, default: false },
})
const emit = defineEmits(['open', 'dragStart', 'dragEnd'])

function onDragStart(e) {
  e.dataTransfer.effectAllowed = 'move'
  emit('dragStart', props.task.id)
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
