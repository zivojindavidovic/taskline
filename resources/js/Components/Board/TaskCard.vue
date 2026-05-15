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
      <span v-if="task.due_date" :class="['chip', isDeadlineUrgent ? 'urgent' : '']">
        <CalendarIcon class="w-4 h-4" />
        {{ dueDateLabel }}
      </span>
      <span v-if="task.comments?.length" class="chip">
        <CommentIcon class="w-4 h-4" />{{ task.comments.length }}
      </span>
      <span class="spacer" />
      <Avatar v-if="task.assignee" :name="task.assignee.name" size="sm" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Avatar from '@/Components/UI/Avatar.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import { CalendarIcon, CommentIcon } from '@/Components/UI/Icons.vue'

const props = defineProps({
  task:     { type: Object, required: true },
  dragging: { type: Boolean, default: false },
})
const emit = defineEmits(['open', 'dragStart', 'dragEnd'])

function onDragStart(e) {
  e.dataTransfer.effectAllowed = 'move'
  emit('dragStart', props.task.id)
}

function fmtD(d) {
  if (!d) return ''
  const date = new Date(d)
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

// Deadline urgency
const deadlineDiff = computed(() => {
  if (!props.task.due_date) return null
  const due = new Date(props.task.due_date)
  due.setHours(23, 59, 59, 999)
  return Math.ceil((due.getTime() - Date.now()) / (1000 * 60 * 60 * 24))
})

const isDeadlineUrgent = computed(() =>
  !props.task.completed && deadlineDiff.value !== null && deadlineDiff.value <= 1
)

const dueDateLabel = computed(() => {
  const { start_date, due_date } = props.task
  if (!due_date) return ''
  if (start_date && start_date !== due_date) return `${fmtD(start_date)}–${fmtD(due_date)}`
  const d = deadlineDiff.value
  if (props.task.completed || d === null) return fmtD(due_date)
  if (d < 0) return Math.abs(d) === 1 ? '1 day overdue' : `${Math.abs(d)} days overdue`
  if (d === 0) return 'today'
  if (d === 1) return 'tomorrow'
  return fmtD(due_date)
})

</script>
