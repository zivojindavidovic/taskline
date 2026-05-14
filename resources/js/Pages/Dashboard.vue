<template>
  <AppLayout>
    <div class="px-8 py-6 flex flex-col gap-6" style="max-width:1100px;margin:0 auto">
      <!-- Header -->
      <div>
        <h1 class="text-2xl font-semibold" style="color:var(--fg)">Good morning, {{ user.name.split(' ')[0] }}</h1>
        <p class="text-sm mt-0.5" style="color:var(--fg-muted)">Dashboard</p>
      </div>

      <!-- Stats grid -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div
          v-for="s in statsCards"
          :key="s.label"
          class="rounded-xl p-4 flex flex-col gap-1"
          style="background:var(--bg-panel);border:1px solid var(--border)"
        >
          <p class="text-2xl font-bold" style="color:var(--fg)">{{ s.value }}</p>
          <p class="text-xs" style="color:var(--fg-muted)">{{ s.label }}</p>
        </div>
      </div>

      <!-- Two-column layout -->
      <div class="grid md:grid-cols-2 gap-4">
        <!-- Awaiting completion -->
        <div class="rounded-xl flex flex-col" style="background:var(--bg-panel);border:1px solid var(--border)">
          <div class="flex items-center justify-between px-4 pt-4 pb-3" style="border-bottom:1px solid var(--border)">
            <h2 class="text-sm font-semibold" style="color:var(--fg)">Awaiting completion</h2>
            <span
              v-if="awaitingCompletion.length > 0"
              class="text-xs px-2 py-0.5 rounded-full font-medium"
              style="background:var(--status-warn-bg);color:var(--status-warn-fg)"
            >{{ awaitingCompletion.length }}</span>
          </div>
          <div class="flex flex-col divide-y overflow-auto" style="border-color:var(--border)">
            <div
              v-if="awaitingCompletion.length === 0"
              class="px-4 py-6 text-sm text-center"
              style="color:var(--fg-muted)"
            >
              All caught up!
            </div>
            <div
              v-for="task in awaitingCompletion"
              :key="task.id"
              class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors hover:bg-[var(--bg-hover)]"
              @click="openTask(task)"
            >
              <PriorityBadge :priority="task.priority" />
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium truncate" style="color:var(--fg)">{{ task.title }}</p>
                <p class="text-xs mt-0.5" style="color:var(--fg-muted)">{{ task.key }} · {{ task.project_name }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Your tasks -->
        <div class="rounded-xl flex flex-col" style="background:var(--bg-panel);border:1px solid var(--border)">
          <div class="px-4 pt-4 pb-3" style="border-bottom:1px solid var(--border)">
            <h2 class="text-sm font-semibold" style="color:var(--fg)">Your tasks</h2>
          </div>
          <div class="flex flex-col divide-y overflow-auto" style="border-color:var(--border)">
            <div
              v-if="myTasks.length === 0"
              class="px-4 py-6 text-sm text-center"
              style="color:var(--fg-muted)"
            >
              No tasks assigned to you.
            </div>
            <div
              v-for="task in myTasks"
              :key="task.id"
              class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors hover:bg-[var(--bg-hover)]"
              @click="openTask(task)"
            >
              <PriorityBadge :priority="task.priority" />
              <div class="flex-1 min-w-0">
                <p
                  class="text-sm font-medium truncate"
                  :class="{ 'line-through opacity-50': task.completed }"
                  style="color:var(--fg)"
                >{{ task.title }}</p>
                <p class="text-xs mt-0.5" style="color:var(--fg-muted)">
                  {{ task.key }}<span v-if="task.due_date"> · Due {{ formatDate(task.due_date) }}</span>
                </p>
              </div>
              <span
                class="shrink-0 text-xs px-2 py-0.5 rounded-full"
                style="background:var(--bg-sunken);color:var(--fg-muted)"
              >{{ task.column_name }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'

const props = defineProps({
  stats:              { type: Object, default: () => ({}) },
  myTasks:            { type: Array,  default: () => [] },
  awaitingCompletion: { type: Array,  default: () => [] },
})

const page = usePage()
const user = computed(() => page.props.auth.user)

const statsCards = computed(() => [
  { label: 'Open',                value: props.stats.open        ?? 0 },
  { label: 'In progress',         value: props.stats.inProgress  ?? 0 },
  { label: 'Awaiting completion', value: props.stats.awaiting    ?? 0 },
  { label: 'Completed',           value: props.stats.completed   ?? 0 },
])

function openTask(task) {
  if (task.project_id) {
    window.location.href = route('projects.show', task.project_id) + '?task=' + task.id
  }
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}
</script>
