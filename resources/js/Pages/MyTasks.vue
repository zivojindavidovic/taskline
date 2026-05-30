<template>
  <AppLayout>
    <div style="width: 100%; max-width: 880px; margin: 0 auto; padding: 24px 32px">
      <h2 style="font-size: 20px; font-weight: 600; margin: 0 0 16px; color: var(--fg)">My tasks</h2>

      <div v-if="openTasks.length" class="list-card">
        <div
          v-for="task in openTasks"
          :key="task.id"
          class="task-row"
          @click="openTask(task)"
        >
          <span class="id">{{ task.key }}</span>
          <PriorityBadge :priority="task.priority" />
          <span class="task-title">{{ task.title }}</span>
          <span class="meta">{{ task.column_name }}</span>
          <span class="meta">{{ task.project_key }}</span>
        </div>
      </div>

      <div
        v-else
        class="rounded-xl flex flex-col items-center justify-center text-center px-6 py-16"
        style="border: 1px solid var(--border)"
      >
        <CheckIcon class="w-6 h-6 mb-3" style="color: var(--fg-subtle)" />
        <p class="text-sm font-medium" style="color: var(--fg)">No tasks assigned to you</p>
        <p class="text-xs mt-1" style="color: var(--fg-muted)">
          Tasks assigned to you will show up here.
        </p>
      </div>
    </div>

    <GlobalTaskPanel :task-id="activeTaskId" @close="activeTaskId = null" />
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import GlobalTaskPanel from '@/Components/Task/GlobalTaskPanel.vue'
import { CheckIcon } from '@/Components/UI/Icons.vue'

const props = defineProps({
  tasks: { type: Array, default: () => [] },
})

const openTasks = computed(() => props.tasks.filter(t => !t.completed))

const activeTaskId = ref(null)

function openTask(task) {
  activeTaskId.value = task.id
}
</script>
