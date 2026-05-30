<template>
  <AppLayout>
    <div class="px-8 py-6" style="max-width: 880px; margin: 0 auto">
      <h2 class="text-xl font-semibold mb-1" style="color: var(--fg)">Inbox</h2>
      <p class="text-sm mb-4" style="color: var(--fg-muted)">Notifications across all your projects.</p>

      <div v-if="notifications.length" class="rounded-xl overflow-hidden" style="border: 1px solid var(--border)">
        <div
          v-for="(n, i) in notifications"
          :key="n.id"
          class="flex items-start gap-3 px-4 py-3 cursor-pointer"
          :style="i > 0 ? 'border-top: 1px solid var(--border)' : ''"
          style="transition: background 80ms"
          @mouseenter="e => e.currentTarget.style.background = 'var(--bg-hover)'"
          @mouseleave="e => e.currentTarget.style.background = ''"
          @click="openNotification(n)"
        >
          <Avatar :name="n.actor" size="sm" class="shrink-0 mt-0.5" />

          <div class="flex-1 min-w-0">
            <p class="text-sm" style="color: var(--fg)">
              <strong>{{ n.actor }}</strong>
              {{ n.verb }}
              <strong class="font-mono">{{ n.target }}</strong>
            </p>
            <p v-if="n.excerpt" class="text-xs mt-0.5 truncate" style="color: var(--fg-muted)">{{ n.excerpt }}</p>
          </div>

          <div class="text-xs shrink-0" style="color: var(--fg-subtle)">{{ n.time }}</div>
        </div>
      </div>

      <div
        v-else
        class="rounded-xl flex flex-col items-center justify-center text-center px-6 py-16"
        style="border: 1px solid var(--border)"
      >
        <InboxIcon class="w-6 h-6 mb-3" style="color: var(--fg-subtle)" />
        <p class="text-sm font-medium" style="color: var(--fg)">You're all caught up</p>
        <p class="text-xs mt-1" style="color: var(--fg-muted)">
          Mentions, assignments and activity on your tasks will show up here.
        </p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import { InboxIcon } from '@/Components/UI/Icons.vue'

const props = defineProps({
  notifications: { type: Array, default: () => [] },
})

function openNotification(n) {
  if (n.task_id && n.project_id) {
    router.visit(route('projects.show', n.project_id) + '?task=' + n.task_id)
  }
}
</script>
