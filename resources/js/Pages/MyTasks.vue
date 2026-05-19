<template>
  <AppLayout>
    <div class="px-8 py-6 max-w-3xl mx-auto flex flex-col gap-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold" style="color:var(--fg)">My tasks</h1>
          <p class="text-sm mt-0.5" style="color:var(--fg-muted)">{{ tasks.length }} task{{ tasks.length !== 1 ? 's' : '' }} assigned to you</p>
        </div>

        <!-- Filter: hide completed -->
        <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--fg-muted)">
          <input type="checkbox" v-model="hideCompleted" class="rounded" />
          Hide completed
        </label>
      </div>

      <!-- Grouped task list -->
      <template v-if="groupedTasks.length > 0">
        <div v-for="group in groupedTasks" :key="group.label" class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <PriorityBadge :priority="group.priority" show-label />
            <span class="text-xs" style="color:var(--fg-muted)">{{ group.tasks.length }}</span>
          </div>
          <div class="rounded-xl overflow-hidden" style="border:1px solid var(--border)">
            <div
              v-for="(task, i) in group.tasks"
              :key="task.id"
              class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors hover:bg-[var(--bg-hover)]"
              :style="i > 0 ? 'border-top:1px solid var(--border)' : ''"
              @click="openTask(task)"
            >
              <!-- Completed circle -->
              <button
                type="button"
                class="w-4 h-4 rounded-full border-2 shrink-0 flex items-center justify-center transition-colors"
                :style="task.completed
                  ? 'border-color:var(--status-done-fg);background:var(--status-done-fg)'
                  : 'border-color:var(--border-strong);background:transparent'"
                @click.stop="toggleComplete(task)"
              >
                <CheckIcon v-if="task.completed" class="w-2.5 h-2.5" style="color:#fff" />
              </button>

              <!-- Key -->
              <span class="text-xs font-mono shrink-0" style="color:var(--fg-muted)">{{ task.key }}</span>

              <!-- Title -->
              <p
                class="flex-1 text-sm font-medium truncate"
                :class="{ 'line-through opacity-40': task.completed }"
                style="color:var(--fg)"
              >{{ task.title }}</p>

              <!-- Project badge -->
              <span
                class="shrink-0 text-xs px-2 py-0.5 rounded-full"
                style="background:var(--bg-sunken);color:var(--fg-muted)"
              >{{ task.project_key }}</span>

              <!-- Due date -->
              <span
                v-if="task.due_date"
                class="shrink-0 text-xs"
                :style="dueLabelFor(task).urgent ? 'color:var(--status-blocked);font-weight:500' : 'color:var(--fg-muted)'"
              >{{ dueLabelFor(task).label }}</span>
            </div>
          </div>
        </div>
      </template>

      <div v-else class="text-center py-16 text-sm" style="color:var(--fg-muted)">
        {{ hideCompleted ? 'No open tasks assigned to you.' : 'No tasks assigned to you.' }}
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import { CheckIcon } from '@/Components/UI/Icons.vue'
import { formatDueDate } from '@/utils/dueDate'

const props = defineProps({
  tasks: { type: Array, default: () => [] },
})

const hideCompleted = ref(false)

const PRIORITY_ORDER = ['urgent', 'high', 'med', 'low']

const visibleTasks = computed(() =>
  hideCompleted.value ? props.tasks.filter(t => !t.completed) : props.tasks
)

const groupedTasks = computed(() => {
  const groups = {}
  visibleTasks.value.forEach(t => {
    if (!groups[t.priority]) groups[t.priority] = []
    groups[t.priority].push(t)
  })
  return PRIORITY_ORDER
    .filter(p => groups[p]?.length)
    .map(p => ({ priority: p, tasks: groups[p] }))
})

function openTask(task) {
  if (task.project_id) {
    window.location.href = route('projects.show', task.project_id) + '?task=' + task.id
  }
}

function toggleComplete(task) {
  if (task.completed) {
    router.post(route('tasks.uncomplete', task.id), {}, { preserveScroll: true })
  } else {
    router.post(route('tasks.complete', task.id), {}, { preserveScroll: true })
  }
}

function dueLabelFor(task) {
  return formatDueDate(task.due_date, task.start_date, task.completed)
}
</script>
