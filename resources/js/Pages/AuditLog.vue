<template>
  <AppLayout>
    <div class="px-8 py-6 max-w-3xl mx-auto flex flex-col gap-6">
      <!-- Header -->
      <div>
        <h1 class="text-xl font-semibold" style="color:var(--fg)">Audit log</h1>
        <p class="text-sm mt-0.5" style="color:var(--fg-muted)">Activity across your workspace</p>
      </div>

      <!-- Timeline -->
      <div v-if="logs.data?.length > 0" class="flex flex-col">
        <!-- Group by date -->
        <template v-for="group in groupedLogs" :key="group.date">
          <div class="flex items-center gap-3 my-3">
            <div class="flex-1 h-px" style="background:var(--border)" />
            <span class="text-xs font-medium shrink-0" style="color:var(--fg-muted)">{{ group.date }}</span>
            <div class="flex-1 h-px" style="background:var(--border)" />
          </div>

          <div
            class="rounded-xl overflow-hidden"
            style="border:1px solid var(--border)"
          >
            <div
              v-for="(log, i) in group.entries"
              :key="log.id"
              class="flex items-start gap-3 px-4 py-3"
              :style="i > 0 ? 'border-top:1px solid var(--border)' : ''"
            >
              <Avatar :name="log.user?.name ?? '?'" size="sm" class="shrink-0 mt-0.5" />
              <div class="flex-1 min-w-0">
                <p class="text-sm" style="color:var(--fg)">
                  <strong>{{ log.user?.name }}</strong>
                  {{ actionLabel(log) }}
                  <span v-if="log.task" class="font-medium" style="color:var(--accent)">{{ log.task.key }}</span>
                </p>
                <p class="text-xs mt-0.5" style="color:var(--fg-muted)">
                  {{ log.project?.name }} · {{ formatTime(log.created_at) }}
                </p>
              </div>

              <!-- Project badge -->
              <span
                class="shrink-0 text-xs px-2 py-0.5 rounded-full"
                style="background:var(--bg-sunken);color:var(--fg-muted)"
              >{{ log.project?.key }}</span>
            </div>
          </div>
        </template>
      </div>

      <!-- Empty state -->
      <div v-else class="text-center py-16 text-sm" style="color:var(--fg-muted)">
        No activity yet.
      </div>

      <!-- Pagination -->
      <div v-if="logs.last_page > 1" class="flex items-center justify-center gap-2">
        <button
          type="button"
          class="px-3 h-8 rounded-lg text-sm border"
          style="border-color:var(--border);color:var(--fg-muted);background:var(--bg-panel)"
          :disabled="logs.current_page === 1"
          @click="changePage(logs.current_page - 1)"
        >Previous</button>
        <span class="text-sm" style="color:var(--fg-muted)">
          Page {{ logs.current_page }} of {{ logs.last_page }}
        </span>
        <button
          type="button"
          class="px-3 h-8 rounded-lg text-sm border"
          style="border-color:var(--border);color:var(--fg-muted);background:var(--bg-panel)"
          :disabled="logs.current_page === logs.last_page"
          @click="changePage(logs.current_page + 1)"
        >Next</button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Avatar from '@/Components/UI/Avatar.vue'

const props = defineProps({
  logs: { type: Object, default: () => ({ data: [], current_page: 1, last_page: 1 }) },
})

const ACTION_LABELS = {
  'task.created':        'created task',
  'task.completed':      'completed task',
  'task.uncompleted':    'reopened task',
  'task.moved':          'moved task',
  'task.assigned':       'assigned task',
  'task.priority_changed': 'changed priority on',
  'task.renamed':        'renamed task',
  'task.tags_updated':   'updated tags on task',
  'task.deleted':        'deleted a task in',
  'sprint.created':      'created sprint',
  'sprint.locked':       'locked sprint',
  'sprint.unlocked':     'unlocked sprint',
  'project.created':     'created project',
  'column.created':      'added column',
  'column.deleted':      'deleted column',
}

function actionLabel(log) {
  return ACTION_LABELS[log.action] ?? log.action
}

const groupedLogs = computed(() => {
  const groups = {}
  ;(props.logs.data ?? []).forEach(log => {
    const d = new Date(log.created_at)
    const key = d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })
    if (!groups[key]) groups[key] = []
    groups[key].push(log)
  })
  return Object.entries(groups).map(([date, entries]) => ({ date, entries }))
})

function formatTime(dt) {
  return new Date(dt).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
}

function changePage(page) {
  router.get(route('audit'), { page }, { preserveScroll: true })
}
</script>
