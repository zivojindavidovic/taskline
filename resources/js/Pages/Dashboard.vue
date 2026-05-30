<template>
  <AppLayout>
    <div class="dash">
      <!-- Greeting -->
      <div>
        <div class="dash-greeting">{{ today }}</div>
        <h1>Good {{ timeOfDay }}, {{ firstName }}</h1>
      </div>

      <!-- Stats grid -->
      <div class="stat-grid">
        <div v-for="s in statsCards" :key="s.label" class="stat">
          <span class="label">{{ s.label }}</span>
          <span class="value">{{ s.value }}</span>
          <span class="delta">{{ s.delta }}</span>
        </div>
      </div>

      <!-- Awaiting completion (hidden when there's nothing to confirm) -->
      <div v-if="awaitingCompletion.length > 0" class="list-card">
        <div class="head">
          <CheckIcon style="width:15px;height:15px;flex-shrink:0" />
          <span class="title">Awaiting completion</span>
          <span style="font-size:12px;color:var(--fg-subtle)">
            {{ awaitingCompletion.length }} tasks in Done need to be marked complete
          </span>
        </div>
        <div
          v-for="task in awaitingCompletion"
          :key="task.id"
          class="task-row"
          @click="openTask(task)"
        >
          <span class="id">{{ task.key }}</span>
          <span class="status-dot" style="background:var(--status-done)"></span>
          <span class="task-title">{{ task.title }}</span>
          <span class="meta">{{ task.assignee_name || 'Unassigned' }}</span>
        </div>
      </div>

      <!-- Your tasks -->
      <div class="list-card">
        <div class="head">
          <UserIcon style="width:15px;height:15px;flex-shrink:0" />
          <span class="title">Your tasks</span>
          <button
            class="btn ghost sm"
            style="display:inline-flex;align-items:center;gap:4px;padding:0 8px"
            @click="viewAllTasks"
          >
            View all <ArrowRightIcon style="width:13px;height:13px" />
          </button>
        </div>
        <div
          v-for="task in myTasks"
          :key="task.id"
          class="task-row"
          @click="openTask(task)"
        >
          <span class="id">{{ task.key }}</span>
          <span class="status-dot" :style="{ background: task.column_color || 'var(--status-todo)' }"></span>
          <span class="task-title">{{ task.title }}</span>
          <span class="meta">{{ task.column_name }}</span>
          <PriorityBadge :priority="task.priority" />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import { CheckIcon, UserIcon, ArrowRightIcon } from '@/Components/UI/Icons.vue'

const props = defineProps({
  stats:              { type: Object, default: () => ({}) },
  myTasks:            { type: Array,  default: () => [] },
  awaitingCompletion: { type: Array,  default: () => [] },
})

const page = usePage()
const user = computed(() => page.props.auth.user)
const firstName = computed(() => user.value.name.split(' ')[0])

const today = computed(() =>
  new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })
)

const timeOfDay = computed(() => {
  const h = new Date().getHours()
  return h < 12 ? 'morning' : h < 17 ? 'afternoon' : 'evening'
})

const projectCount = computed(() => props.stats.projectCount ?? 0)

const statsCards = computed(() => [
  { label: 'Open tasks',            value: props.stats.open ?? 0,            delta: `across ${projectCount.value} project${projectCount.value === 1 ? '' : 's'}` },
  { label: 'In progress',           value: props.stats.inProgress ?? 0,      delta: `${props.stats.inProgressMine ?? 0} assigned to you` },
  { label: 'Awaiting completion',   value: props.stats.awaiting ?? 0,        delta: 'in Done, not yet confirmed' },
  { label: 'Completed this sprint', value: props.stats.completedSprint ?? 0, delta: props.stats.sprintDelta ?? '' },
])

function openTask(task) {
  if (task.project_id) {
    window.location.href = route('projects.show', task.project_id) + '?task=' + task.id
  }
}

function viewAllTasks() {
  router.visit(route('my-tasks'))
}
</script>
